/**
 * Leaving the builder, by Back or Esc: it sends the unsaved changes first and leaves once they're saved. When they can't
 * be sent, it stays and says why, and the editor stays or leaves anyway, without them.
 * See "Opening and leaving" in docs/spec/builder.md
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { Autosave } from './autosave';
import type { BuilderStore, SaveState } from './store';

/** Why the unsaved changes couldn't be sent, as the save state said it. */
export type FailedSave = Extract<SaveState, 'failed' | 'stale' | 'locked'>;

export interface LeavingState {
    /**
     * Why leaving stayed, kept as it was then, since a locked draft loads again meanwhile, until the editor stays or
     * leaves anyway. null while it didn't.
     */
    failedSave: FailedSave | null;
    /** Sends the unsaved changes, then leaves, or stays when they can't be sent. */
    leave(): Promise<void>;
    /** Leaves without the changes that couldn't be sent. */
    leaveAnyway(): void;
    stay(): void;
}

export type LeavingStore = StoreApi<LeavingState>;

export function createLeaving(store: BuilderStore, autosave: Pick<Autosave, 'flush'>, go: () => void): LeavingStore {
    return createStore<LeavingState>()((set) => ({
        failedSave: null,

        async leave() {
            const saved = await autosave.flush().catch(() => false);
            const { unsaved, saveState } = store.getState();

            // A save that can't go through with nothing left to send, such as after colleagues' changes couldn't be
            // merged into a working copy without changes of its own, loses nothing
            if (saved || unsaved.length === 0) {
                go();
            } else {
                set({ failedSave: saveState === 'stale' || saveState === 'locked' ? saveState : 'failed' });
            }
        },

        leaveAnyway() {
            set({ failedSave: null });
            go();
        },

        stay() {
            set({ failedSave: null });
        },
    }));
}
