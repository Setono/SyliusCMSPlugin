import { mergeChanges, type Change } from './changeSet';
import type { BuilderStore } from './store';
import type { EntryStatus } from './types';

export interface Autosave {
    /** Sends the unsaved changes now, after any save in flight. Resolves to whether everything got saved. */
    flush(): Promise<boolean>;
    dispose(): void;
}

export interface AutosaveOptions {
    /** How long after the last edit the changes are sent, in milliseconds. */
    delay?: number;
}

/**
 * Saves the working copy's changes a moment after each edit. Only one save is in flight at a time:
 * changes made during a save go into the next one, and a failed save's changes stay for the next.
 */
export function createAutosave(
    store: BuilderStore,
    send: (changes: readonly Change[]) => Promise<EntryStatus>,
    { delay = 500 }: AutosaveOptions = {},
): Autosave {
    let timer: ReturnType<typeof setTimeout> | undefined;
    let inFlight: Promise<boolean> | undefined;

    const cancelTimer = (): void => {
        if (timer !== undefined) {
            clearTimeout(timer);
            timer = undefined;
        }
    };

    const save = async (): Promise<boolean> => {
        while (inFlight !== undefined) {
            await inFlight;
        }

        const changes = store.getState().unsaved;
        if (changes.length === 0) {
            return true;
        }

        store.setState({ unsaved: [], saveState: 'saving' });

        inFlight = send(changes)
            .then(
                (status) => {
                    store.setState((state) => ({ status, saveState: state.unsaved.length > 0 ? 'saving' : 'saved' }));

                    return true;
                },
                () => {
                    store.setState((state) => ({ unsaved: mergeChanges(changes, state.unsaved), saveState: 'failed' }));

                    return false;
                },
            )
            .finally(() => {
                inFlight = undefined;
            });

        return inFlight;
    };

    const unsubscribe = store.subscribe((state, previous) => {
        if (state.revision === previous.revision) {
            return;
        }

        // An edit can also cancel out the unsaved changes, such as adding a block and removing it again.
        const unsaved = state.unsaved.length > 0 || inFlight !== undefined;
        store.setState({ saveState: unsaved ? 'saving' : 'saved' });

        cancelTimer();
        if (state.unsaved.length > 0) {
            timer = setTimeout(() => {
                timer = undefined;
                void save();
            }, delay);
        }
    });

    return {
        flush() {
            cancelTimer();

            return save();
        },

        dispose() {
            cancelTimer();
            unsubscribe();
        },
    };
}
