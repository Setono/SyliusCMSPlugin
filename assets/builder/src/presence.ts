/**
 * Who else has the entry open. The builder tells the server every few seconds that the editor still has it open, and
 * the server answers with who else does, dropping people who stopped saying so. The answer also carries the working
 * copy's lock token, so a builder that's behind pulls in what colleagues saved. See "Top bar" in docs/spec/builder.md
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { Autosave } from './autosave';
import type { BuilderStore } from './store';
import type { Editor, PresenceResponse } from './types';

export interface PresenceState {
    /** The others who have the entry open. */
    editors: Editor[];
}

export type PresenceStore = StoreApi<PresenceState>;

export interface Presence {
    store: PresenceStore;
    dispose(): void;
}

export interface PresenceOptions {
    /** How often the builder says the editor still has the entry open, in milliseconds. */
    interval?: number;
}

export function createPresence(
    builder: BuilderStore,
    autosave: Pick<Autosave, 'pull'>,
    send: () => Promise<PresenceResponse>,
    editors: Editor[],
    { interval = 5000 }: PresenceOptions = {},
): Presence {
    const store = createStore<PresenceState>()(() => ({ editors }));
    let stopped = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const beat = async (): Promise<void> => {
        try {
            const answer = await send();
            if (stopped) {
                return;
            }

            store.setState({ editors: answer.editors });
            if (answer.lockToken > builder.getState().lockToken) {
                void autosave.pull(answer.lockToken);
            }
        } catch {
            // The next beat tries again
        }

        if (!stopped) {
            timer = setTimeout(() => void beat(), interval);
        }
    };

    void beat();

    return {
        store,
        dispose() {
            stopped = true;
            clearTimeout(timer);
        },
    };
}
