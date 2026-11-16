import type { Entry, FieldValues, JsonObject, JsonValue, TranslationSource, TranslationState } from './types';

export function isJsonObject(value: JsonValue | undefined): value is JsonObject {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** An empty string or an empty list counts as no value, which the stored format leaves out. */
export function normalizeValue(value: JsonValue | undefined): JsonValue | null {
    if (value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
        return null;
    }

    return value;
}

/** A field's value in the edited locale, or its one value when it's shared. */
export function readFieldValue(fields: FieldValues, field: string, translatable: boolean, locale: string): JsonValue | undefined {
    const stored = fields[field];
    if (!translatable) {
        return stored;
    }

    return isJsonObject(stored) ? stored[locale] : undefined;
}

/**
 * Writes a field's value in the edited locale, or its one value when it's shared, leaving out what's
 * empty. Called on a working copy inside an Immer recipe, so it changes only what it has to.
 */
export function writeFieldValue(fields: FieldValues, field: string, translatable: boolean, locale: string, value: JsonValue | null): void {
    const normalized = normalizeValue(value);

    if (!translatable) {
        if (normalized === null) {
            delete fields[field];
        } else {
            fields[field] = normalized;
        }

        return;
    }

    const values = fields[field];
    if (!isJsonObject(values)) {
        if (normalized !== null) {
            fields[field] = { [locale]: normalized };
        }

        return;
    }

    if (normalized === null) {
        delete values[locale];
    } else {
        values[locale] = normalized;
    }

    if (Object.keys(values).length === 0) {
        delete fields[field];
    }
}

/**
 * Writes a value of an entry field in a locale, such as a translation's title. A locale the entry has no translation in
 * gets one, switched the way it's given, unless the value is empty. An empty value is kept as null, the way the entry
 * format lists a translation's fields. Called on a working copy inside an Immer recipe.
 */
export function writeEntryValue(entry: Entry, locale: string, field: string, value: JsonValue | null, shownIfAdded: boolean): void {
    const normalized = normalizeValue(value);
    const translation = entry.translations[locale];

    if (translation === undefined) {
        if (normalized !== null) {
            entry.translations[locale] = { shown: shownIfAdded, fields: { [field]: normalized }, translated: {} };
        }

        return;
    }

    if (!jsonEqual(translation.fields[field], normalized)) {
        translation.fields[field] = normalized;
    }
}

/**
 * Writes where a value in a locale was translated from, or that it has no source when source is null, into a block's
 * or a layout's translation state: field name, then locale. A field with no source in any locale is left out. Called
 * inside an Immer recipe, so it changes only what it has to.
 */
export function writeTranslated(translated: TranslationState, field: string, locale: string, source: TranslationSource | null): void {
    const locales = translated[field];
    if (source === null) {
        if (locales !== undefined && locale in locales) {
            delete locales[locale];
            if (Object.keys(locales).length === 0) {
                delete translated[field];
            }
        }

        return;
    }

    const current = locales?.[locale];
    if (current?.from === source.from && current.fingerprint === source.fingerprint) {
        return;
    }

    if (locales === undefined) {
        translated[field] = { [locale]: { ...source } };
    } else {
        locales[locale] = { ...source };
    }
}

/**
 * Writes where a value of an entry field in a locale, such as a translation's title, was translated from, or that it
 * has no source. A locale the entry has no translation in gets one, switched the way it's given, unless there's no
 * source to write. Called inside an Immer recipe.
 */
export function writeEntryTranslated(entry: Entry, locale: string, field: string, source: TranslationSource | null, shownIfAdded: boolean): void {
    const translation = entry.translations[locale];
    if (translation !== undefined) {
        const current = translation.translated[field];
        if (source === null) {
            if (current !== undefined) {
                delete translation.translated[field];
            }
        } else if (current?.from !== source.from || current.fingerprint !== source.fingerprint) {
            translation.translated[field] = { ...source };
        }

        return;
    }

    if (source !== null) {
        entry.translations[locale] = { shown: shownIfAdded, fields: {}, translated: { [field]: { ...source } } };
    }
}

export function jsonEqual(a: JsonValue | undefined, b: JsonValue | undefined): boolean {
    if (a === b) {
        return true;
    }

    if (Array.isArray(a) || Array.isArray(b)) {
        return Array.isArray(a) && Array.isArray(b) && a.length === b.length && a.every((item, i) => jsonEqual(item, b[i]));
    }

    if (isJsonObject(a) && isJsonObject(b)) {
        const keys = Object.keys(a);

        return keys.length === Object.keys(b).length && keys.every((key) => Object.hasOwn(b, key) && jsonEqual(a[key], b[key]));
    }

    return false;
}

/** A value as text: the text of rich text, and anything else as it's written. */
export function plainText(value: JsonValue): string {
    if (typeof value === 'string') {
        return value;
    }

    if (isJsonObject(value) && value.type === 'doc') {
        return textOf(value).trim();
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function textOf(node: JsonValue | undefined): string {
    if (!isJsonObject(node)) {
        return '';
    }

    if (typeof node.text === 'string') {
        return node.text;
    }

    const children = Array.isArray(node.content) ? node.content.map(textOf).join('') : '';

    return node.type === 'paragraph' || node.type === 'heading' ? `${children}\n` : children;
}
