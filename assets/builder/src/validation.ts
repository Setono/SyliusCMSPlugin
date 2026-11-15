/**
 * What publish would refuse in the working copy, as the server last validated it, and where it shows: invalid blocks
 * are flagged in the tree, and their errors show in the right pane, next to the fields.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { BuilderStore } from './store';
import type { StoredBlock, ValidationError } from './types';

export interface ValidationState {
    /** The errors the server last found, which point at blocks by key. */
    errors: ValidationError[];
    setErrors(errors: ValidationError[]): void;
}

export type ValidationStore = StoreApi<ValidationState>;

export function createValidationStore(errors: ValidationError[] = []): ValidationStore {
    return createStore<ValidationState>()((set) => ({
        errors,
        setErrors(next) {
            set({ errors: next });
        },
    }));
}

export interface Validation {
    /** Asks the server to validate the working copy it holds, and keeps the errors. */
    validate(): Promise<void>;
    dispose(): void;
}

/**
 * Keeps the errors in step with the working copy the server holds: it validates when a save has landed, and when a
 * working copy is loaded in place of the one being edited, such as after discarding the draft. An answer to an older
 * request than the last is ignored. Invalid content still saves: this only shows what publish would refuse.
 */
export function createValidation(store: BuilderStore, validation: ValidationStore, send: () => Promise<ValidationError[]>): Validation {
    let requests = 0;

    const validate = async (): Promise<void> => {
        const request = ++requests;

        try {
            const errors = await send();
            if (request === requests) {
                validation.getState().setErrors(errors);
            }
        } catch {
            // The errors shown stay as they were. A refused publish lists them anyway.
        }
    };

    const unsubscribe = store.subscribe((state, previous) => {
        const saved = state.saveState === 'saved' && previous.saveState !== 'saved';
        // A load replaces the working copy without an edit
        const loaded = state.entry !== previous.entry && state.changeCount === previous.changeCount;
        if (saved || loaded) {
            void validate();
        }
    });

    return { validate, dispose: unsubscribe };
}

/** Whether the block's row is flagged: it has an error of its own, or in one of its fields. */
export function blockHasErrors(errors: readonly ValidationError[], key: string): boolean {
    return errors.some((error) => error.block === key);
}

/** The first block in tree order, depth first, that has an error, or null when no block of the tree has one. */
export function firstBlockWithError(blocks: readonly StoredBlock[], errors: readonly ValidationError[]): string | null {
    for (const block of blocks) {
        if (blockHasErrors(errors, block.key)) {
            return block.key;
        }

        const below = firstBlockWithError(block.children, errors);
        if (below !== null) {
            return below;
        }
    }

    return null;
}

/** The messages of one of a block's fields. Those of another locale than the edited one say which locale they're in. */
export function fieldMessages(errors: readonly ValidationError[], key: string, field: string, locale: string): string[] {
    return errors.filter((error) => error.block === key && error.field === field).map((error) => messageIn(error, locale));
}

/** The messages about a block as a whole, such as a block type it can't hold. */
export function blockMessages(errors: readonly ValidationError[], key: string): string[] {
    return errors.filter((error) => error.block === key && error.field === null).map((error) => error.message);
}

/** The entry's own messages, each naming the entry field it's in, if any. */
export function entryMessages(errors: readonly ValidationError[], locale: string): string[] {
    return errors.filter((error) => error.block === null).map((error) => (error.field === null ? '' : `${error.field}: `) + messageIn(error, locale));
}

function messageIn(error: ValidationError, locale: string): string {
    return error.locale === null || error.locale === locale ? error.message : `${error.message} (${error.locale})`;
}
