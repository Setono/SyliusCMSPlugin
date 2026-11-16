/**
 * The fallback chain in the builder: which values count as missing, and the value an empty translatable field falls
 * back to, which it shows as a placeholder. The same rules as the server's (src/Localization/FallbackValues.php).
 * Pure functions, with no React and no I/O. See "Fallback chain" in docs/spec/localization.md
 */
import type { JsonValue } from './types';
import { isJsonObject } from './values';

/** The value a field falls back to, and the locale it's from. */
export interface Fallback {
    locale: string;
    value: JsonValue;
}

/** The value an empty translatable field falls back to, as the builder shows it: with the name of the locale it's from. */
export interface FieldFallback {
    value: JsonValue;
    localeName: string;
}

/**
 * Nothing, null, an empty string or list, and an empty rich text document, which holds nothing but empty blocks,
 * whitespace and line breaks, count as missing.
 */
export function isMissing(value: JsonValue | undefined): boolean {
    if (value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
        return true;
    }

    return isJsonObject(value) && value.type === 'doc' && isEmptyNode(value);
}

/** What Tiptap's isNodeEmpty() answers when it ignores whitespace, as the server's rich text schema does. */
function isEmptyNode(node: JsonValue | undefined): boolean {
    if (!isJsonObject(node)) {
        return true;
    }

    switch (node.type) {
        case 'text':
            return typeof node.text !== 'string' || node.text.trim() === '';
        case 'horizontalRule':
            return false;
        default:
            return !Array.isArray(node.content) || node.content.every(isEmptyNode);
    }
}

/**
 * The value a translatable field falls back to in the first locale of the chain: the first value that isn't missing in
 * the locales after it, in order, or undefined when there's none.
 *
 * @param values what the field stores: a map from locale to value
 * @param chain the edited locale first, then the locales it falls back through
 */
export function fallbackValue(values: JsonValue | undefined, chain: readonly string[]): Fallback | undefined {
    if (!isJsonObject(values)) {
        return undefined;
    }

    for (const locale of chain.slice(1)) {
        const value = values[locale];
        if (!isMissing(value)) {
            return { locale, value: value as JsonValue };
        }
    }

    return undefined;
}
