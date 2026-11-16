import type { Entry, FieldValues, JsonObject, JsonValue } from './types';

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
