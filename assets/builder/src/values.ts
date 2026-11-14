import type { FieldValues, JsonObject, JsonValue } from './types';

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
