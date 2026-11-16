import { ApiError } from './api';
import { mergeChanges, type Change } from './changeSet';
import type { BuilderStore } from './store';
import type { SaveResponse, ValidationError } from './types';

export interface Autosave {
    /** Sends the unsaved changes now, after any save in flight. Resolves to whether everything got saved. */
    flush(): Promise<boolean>;
    /**
     * Pulls in what colleagues saved, once the working copy on the server has moved on to the lock token: it sends the
     * unsaved changes, or none, and the answer carries theirs. Resolves to whether it got through.
     */
    pull(lockToken: number): Promise<boolean>;
    dispose(): void;
}

export interface AutosaveOptions {
    /** How long after the last edit the changes are sent, in milliseconds. */
    delay?: number;
}

/**
 * Saves the working copy's changes a moment after each edit. Only one save is in flight at a time:
 * changes made during a save go into the next one, and a failed save's changes stay for the next.
 * Each save carries the lock token of the last load or save. When colleagues saved since, the answer carries the
 * working copy with their changes, and the clashes, which the builder pulls in.
 */
export function createAutosave(
    store: BuilderStore,
    send: (changes: readonly Change[], lockToken: number) => Promise<SaveResponse>,
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

    /** Sends the unsaved changes, or none when pulling up to the lock token. */
    const save = async (pullTo?: number): Promise<boolean> => {
        while (inFlight !== undefined) {
            await inFlight;
        }

        const { unsaved: changes, lockToken, saveState, entry: sent } = store.getState();
        if (saveState === 'stale') {
            return false;
        }

        if (changes.length === 0 && (pullTo === undefined || lockToken >= pullTo)) {
            return true;
        }

        const saving = changes.length > 0;
        store.setState({ unsaved: [], saveState: saving ? 'saving' : saveState });

        inFlight = send(changes, lockToken)
            .then(
                (answer) => {
                    store.setState((state) => ({ status: answer.status, lockToken: answer.lockToken, refusedSlugs: refusedSlugs(state.refusedSlugs, changes, answer.refused ?? []) }));
                    if (answer.entry !== undefined) {
                        // Edits made while the save was in flight were made on the working copy as it was sent
                        store.getState().pullIn(answer.entry, answer.clashes ?? [], sent);
                    }

                    store.setState((state) => ({ saveState: state.unsaved.length > 0 ? 'saving' : saving ? 'saved' : saveState }));

                    return true;
                },
                (error: unknown) => {
                    // A 409: the server can't merge onto what colleagues saved, so no later save can go through either
                    const stale = error instanceof ApiError && error.status === 409;
                    store.setState((state) => ({ unsaved: mergeChanges(changes, state.unsaved), saveState: stale ? 'stale' : saving ? 'failed' : saveState }));

                    return false;
                },
            )
            .finally(() => {
                inFlight = undefined;
            });

        return inFlight;
    };

    const unsubscribe = store.subscribe((state, previous) => {
        if (state.changeCount === previous.changeCount) {
            return;
        }

        if (state.saveState === 'stale') {
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

        pull(lockToken) {
            cancelTimer();

            return save(lockToken);
        },

        dispose() {
            cancelTimer();
            unsubscribe();
        },
    };
}

/**
 * The slugs refused after a save: a slug the save sent in a locale replaces the refusal there, which stands when the
 * server refused that one too. A refusal of a locale the save sent no slug in stands.
 */
function refusedSlugs(before: Record<string, string>, sent: readonly Change[], refused: readonly ValidationError[]): Record<string, string> {
    const sentIn = new Set(sent.flatMap((change) => (change.op === 'set_entry_value' && change.field === 'slug' && change.locale !== null ? [change.locale] : [])));
    if (sentIn.size === 0 && refused.length === 0) {
        return before;
    }

    const after = Object.fromEntries(Object.entries(before).filter(([locale]) => !sentIn.has(locale)));
    for (const { locale, message } of refused) {
        if (locale !== null) {
            after[locale] = message;
        }
    }

    return after;
}
