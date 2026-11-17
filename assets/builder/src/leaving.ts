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
    /**
     * Sends the unsaved changes, then leaves, or stays when they can't be sent: for where the builder was opened from, or
     * for another page, such as a snippet's builder.
     */
    leave(to?: string): Promise<void>;
    /** Leaves without the changes that couldn't be sent, for where the last leave was for. */
    leaveAnyway(): void;
    stay(): void;
}

export type LeavingStore = StoreApi<LeavingState>;

/**
 * @param go goes to the page given, or where the builder was opened from without one
 */
export function createLeaving(store: BuilderStore, autosave: Pick<Autosave, 'flush'>, go: (to?: string) => void): LeavingStore {
    // Where the last leave was for, which leaving anyway goes to
    let target: string | undefined;
    const goTo = (): void => (target === undefined ? go() : go(target));

    return createStore<LeavingState>()((set) => ({
        failedSave: null,

        async leave(to) {
            target = to;
            const saved = await autosave.flush().catch(() => false);
            const { unsaved, saveState } = store.getState();

            // A save that can't go through with nothing left to send, such as after colleagues' changes couldn't be
            // merged into a working copy without changes of its own, loses nothing
            if (saved || unsaved.length === 0) {
                goTo();
            } else {
                set({ failedSave: saveState === 'stale' || saveState === 'locked' ? saveState : 'failed' });
            }
        },

        leaveAnyway() {
            set({ failedSave: null });
            goTo();
        },

        stay() {
            set({ failedSave: null });
        },
    }));
}
