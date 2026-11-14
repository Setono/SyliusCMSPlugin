import { ApiError } from './api';
import type { Autosave } from './autosave';
import { mergeChanges } from './changeSet';
import type { BuilderStore } from './store';
import type { EntryResponse, EntryStatus } from './types';

/** Publishing first saves what's unsaved, then publishes. */
export function createPublish(store: BuilderStore, autosave: Autosave, send: () => Promise<EntryStatus>): () => Promise<void> {
    return async () => {
        if (store.getState().publishState === 'publishing') {
            return;
        }

        store.setState({ publishState: 'publishing' });

        try {
            if (!(await autosave.flush())) {
                throw new Error('The unsaved changes could not be saved.');
            }

            store.setState({ status: await send(), publishState: 'idle', publishRefusal: null });
        } catch (error) {
            // The server answers 422 with the reason when it refuses, such as a BeforeEntryPublish listener's veto.
            const refusal = error instanceof ApiError && error.status === 422 ? error.message : null;
            store.setState({ publishState: 'failed', publishRefusal: refusal });
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

            const { entry, status } = await send();
            store.getState().load(entry, status);
            store.setState({ discardState: 'idle', saveState: 'idle' });
        } catch {
            // The working copy stays as it is, so its unsaved changes get saved after all.
            store.setState((state) => ({ discardState: 'failed', unsaved: mergeChanges(unsaved, state.unsaved) }));
            void autosave.flush();
        }
    };
}
