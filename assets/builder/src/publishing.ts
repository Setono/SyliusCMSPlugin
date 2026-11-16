import { ApiError } from './api';
import type { Autosave } from './autosave';
import { mergeChanges } from './changeSet';
import type { BuilderStore } from './store';
import type { EntryResponse, EntryStatus } from './types';
import { createValidationStore, firstBlockWithError, type ValidationStore } from './validation';

/**
 * Publishing first saves what's unsaved, then publishes. When validation refuses the publish, its errors are shown and
 * the first block with one is selected, or the entry's settings when only the entry has errors.
 */
export function createPublish(
    store: BuilderStore,
    autosave: Autosave,
    send: () => Promise<EntryStatus>,
    validation: ValidationStore = createValidationStore(),
): () => Promise<void> {
    return async () => {
        if (store.getState().publishState === 'publishing') {
            return;
        }

        store.setState({ publishState: 'publishing' });

        try {
            if (!(await autosave.flush())) {
                throw new Error('The unsaved changes could not be saved.');
            }

            // A publish ends the entry's scheduled publish, a failed one too
            store.setState({ status: await send(), publishState: 'idle', publishRefusal: null, schedule: null });
            // What was published passed validation
            validation.getState().setErrors([]);
        } catch (error) {
            // The server answers 422 with the reason when it refuses: with the errors when validation refuses, and
            // with a BeforeEntryPublish listener's veto
            const refused = error instanceof ApiError && error.status === 422;
            store.setState({ publishState: 'failed', publishRefusal: refused ? error.message : null });

            if (refused && error.errors.length > 0) {
                validation.getState().setErrors(error.errors);
                store.getState().selectBlock(firstBlockWithError(store.getState().entry.tree.blocks, error.errors));
            }
        }
    };
}

/**
 * Discarding changes deletes the draft, so the entry goes back to its published content, which the builder then
 * loads. The unsaved changes go with the draft, and a save in flight lands first, so it can't bring the draft back.
 */
export function createDiscard(store: BuilderStore, autosave: Autosave, send: () => Promise<EntryResponse>): () => Promise<void> {
    return async () => {
        if (store.getState().discardState === 'discarding') {
            return;
        }

        const unsaved = store.getState().unsaved;
        store.setState({ discardState: 'discarding', unsaved: [] });

        try {
            // Waits for a save in flight. Nothing is left to send.
            await autosave.flush();

            const { entry, status, lockToken, schedule } = await send();
            store.getState().load(entry, status, lockToken, schedule ?? null);
            store.setState({ discardState: 'idle', saveState: 'idle' });
        } catch {
            // The working copy stays as it is, so its unsaved changes get saved after all.
            store.setState((state) => ({ discardState: 'failed', unsaved: mergeChanges(unsaved, state.unsaved) }));
            void autosave.flush();
        }
    };
}

/**
 * Loads the working copy again in place of the one being edited, when the server can't merge a save onto what
 * colleagues saved. What couldn't be saved goes with it.
 */
export function createReload(store: BuilderStore, fetchEntry: () => Promise<EntryResponse>): () => Promise<void> {
    return async () => {
        const { entry, status, lockToken, schedule } = await fetchEntry();
        store.getState().load(entry, status, lockToken, schedule ?? null);
        store.setState({ saveState: 'idle' });
    };
}

/**
 * Scheduling a publish first saves what's unsaved, then schedules it at a time in UTC, and loads the working copy the
 * server answers with, which the scheduled publish locks. When the server refuses, it says why, and validation's
 * errors are shown as for a refused publish. See "Scheduled publishes" in docs/spec/publishing.md
 */
export function createSchedule(
    store: BuilderStore,
    autosave: Autosave,
    send: (at: string) => Promise<EntryResponse>,
    validation: ValidationStore = createValidationStore(),
): (at: string) => Promise<boolean> {
    return async (at) => {
        if (store.getState().scheduleState === 'scheduling') {
            return false;
        }

        store.setState({ scheduleState: 'scheduling', scheduleRefusal: null });

        try {
            if (!(await autosave.flush())) {
                throw new Error('The unsaved changes could not be saved.');
            }

            const { entry, status, lockToken, schedule } = await send(at);
            store.getState().load(entry, status, lockToken, schedule ?? null);
            store.setState({ scheduleState: 'idle' });
            validation.getState().setErrors([]);

            return true;
        } catch (error) {
            const refused = error instanceof ApiError && error.status === 422;
            store.setState({ scheduleState: 'failed', scheduleRefusal: refused ? error.message : null });

            if (refused && error.errors.length > 0) {
                validation.getState().setErrors(error.errors);
                store.getState().selectBlock(firstBlockWithError(store.getState().entry.tree.blocks, error.errors));
            }

            return false;
        }
    };
}

/** Cancelling the scheduled publish loads the working copy the server answers with, unlocked. */
export function createCancelSchedule(store: BuilderStore, send: () => Promise<EntryResponse>): () => Promise<void> {
    return async () => {
        if (store.getState().cancelScheduleState === 'cancelling') {
            return;
        }

        store.setState({ cancelScheduleState: 'cancelling' });

        try {
            const { entry, status, lockToken, schedule } = await send();
            store.getState().load(entry, status, lockToken, schedule ?? null);
            store.setState({ cancelScheduleState: 'idle', saveState: 'idle' });
        } catch {
            store.setState({ cancelScheduleState: 'failed' });
        }
    };
}
