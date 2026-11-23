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

/**
 * Where an error is, worked out once from where the server says it is: the entry as a whole, such as what its top level
 * holds, or one of its own fields; the layout as a whole or one of its fields; a block as a whole or one of its fields;
 * or one of a block's visibility conditions, by its index, as a whole or one of its parameters.
 */
export type ErrorPlace =
    | { at: 'entry' }
    | { at: 'entryField'; field: string }
    | { at: 'layout' }
    | { at: 'layoutField'; field: string }
    | { at: 'block'; block: string }
    | { at: 'blockField'; block: string; field: string }
    | { at: 'condition'; block: string; condition: number }
    | { at: 'conditionParameter'; block: string; condition: number; field: string };

export function placeOf({ block, field, condition, layout }: ValidationError): ErrorPlace {
    if (block === null) {
        if (layout === true) {
            return field === null ? { at: 'layout' } : { at: 'layoutField', field };
        }

        return field === null ? { at: 'entry' } : { at: 'entryField', field };
    }

    if (condition !== undefined) {
        return field === null ? { at: 'condition', block, condition } : { at: 'conditionParameter', block, condition, field };
    }

    return field === null ? { at: 'block', block } : { at: 'blockField', block, field };
}

/** Whether the block's row is flagged: it has an error of its own, in one of its fields or in one of its conditions. */
export function blockHasErrors(errors: readonly ValidationError[], key: string): boolean {
    return errors.some((error) => {
        const place = placeOf(error);

        return 'block' in place && place.block === key;
    });
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
    return messagesAt(errors, (place) => place.at === 'blockField' && place.block === key && place.field === field, locale);
}

/** The messages about a block as a whole, such as a block type it can't hold. */
export function blockMessages(errors: readonly ValidationError[], key: string): string[] {
    return messagesAt(errors, (place) => place.at === 'block' && place.block === key);
}

/** The messages about one of a block's visibility conditions as a whole, by its index, such as a kind the code no longer has. */
export function conditionMessages(errors: readonly ValidationError[], key: string, index: number): string[] {
    return messagesAt(errors, (place) => place.at === 'condition' && place.block === key && place.condition === index);
}

/** The messages of one of the parameters of a block's visibility condition, such as a date window's end before its start. */
export function conditionFieldMessages(errors: readonly ValidationError[], key: string, index: number, field: string): string[] {
    return messagesAt(errors, (place) => place.at === 'conditionParameter' && place.block === key && place.condition === index && place.field === field);
}

/** The messages about one of the entry's own fields, such as a content type's required field. */
export function entryFieldMessages(errors: readonly ValidationError[], field: string, locale: string): string[] {
    return messagesAt(errors, (place) => place.at === 'entryField' && place.field === field, locale);
}

/** The messages about one of the fields of the layout, such as a required one. Those of another locale say which locale they're in. */
export function layoutFieldMessages(errors: readonly ValidationError[], field: string, locale: string): string[] {
    return messagesAt(errors, (place) => place.at === 'layoutField' && place.field === field, locale);
}

/** The entry fields and whether the layout's fields that the open settings tab shows with their errors. */
export interface ShownAtFields {
    entryFields: readonly string[];
    layoutFields: boolean;
}

/**
 * The entry's own messages, and those of the layout, each naming the field it's in, if any, except those that show at
 * a field of the open settings tab.
 */
export function entryMessages(errors: readonly ValidationError[], locale: string, shown: ShownAtFields = { entryFields: [], layoutFields: false }): string[] {
    return errors.flatMap((error) => {
        const place = placeOf(error);
        switch (place.at) {
            case 'entry':
            case 'layout':
                return [messageIn(error, locale)];
            case 'entryField':
                return shown.entryFields.includes(place.field) ? [] : [`${place.field}: ${messageIn(error, locale)}`];
            case 'layoutField':
                return shown.layoutFields ? [] : [`${place.field}: ${messageIn(error, locale)}`];
            default:
                return [];
        }
    });
}

/** The messages of the errors at a place, saying which locale those of another locale than the edited one are in. */
function messagesAt(errors: readonly ValidationError[], at: (place: ErrorPlace) => boolean, locale?: string): string[] {
    return errors.filter((error) => at(placeOf(error))).map((error) => (locale === undefined ? error.message : messageIn(error, locale)));
}

function messageIn(error: ValidationError, locale: string): string {
    return error.locale === null || error.locale === locale ? error.message : `${error.message} (${error.locale})`;
}
