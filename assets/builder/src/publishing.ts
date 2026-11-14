import type { Autosave } from './autosave';
import type { BuilderStore } from './store';
import type { EntryStatus } from './types';

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

            store.setState({ status: await send(), publishState: 'idle' });
        } catch {
            store.setState({ publishState: 'failed' });
        }
    };
}
