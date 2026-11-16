/**
 * The translation state of an entry's values: which are untranslated or outdated in a locale, and where a value typed
 * in a locale is translated from. The same rules as the server's (src/Translation/TranslatableValues.php), which the
 * entry lists and its overview page count with. Pure functions, with no React and no I/O. See "Completeness and
 * outdated flags" in docs/spec/localization.md and "Translation state" in docs/spec/content-model.md
 */
import type { TranslatableLookup } from './changeSet';
import { fallbackValue, isMissing } from './fallback';
import { fingerprint } from './fingerprint';
import type { Entry, JsonObject, JsonValue, StoredBlock, TranslationSource } from './types';
import { isJsonObject } from './values';

/**
 * Untranslated: missing or empty in the locale while another locale has a value. Outdated: the value it was translated
 * from has changed since. Up to date: anything else with a value.
 */
export type ValueState = 'untranslated' | 'outdated' | 'up_to_date';

/** A value as another locale has it, such as the one an outdated value was translated from. */
export interface LocaleValue {
    locale: string;
    value: JsonValue;
}

/** What an outdated field shows: the value it was translated from as that locale has it now, as the builder shows it. */
export interface OutdatedValue {
    value: JsonValue;
    /** The name of the locale it was translated from. */
    localeName: string;
    onMarkUpToDate(): void;
}

/** How many of an entry's translatable values are untranslated and outdated in a locale. */
export interface LocaleCounts {
    untranslated: number;
    outdated: number;
}

/**
 * The state of a translatable value in a locale, or null when no locale has a value, so there's nothing to translate.
 *
 * @param values what the field stores: a map from locale to value
 * @param translated where each locale's value was translated from
 */
export function valueState(values: JsonValue | undefined, translated: Readonly<Record<string, TranslationSource>> | undefined, locale: string): ValueState | null {
    const byLocale = localeMap(values);
    if (isMissing(byLocale[locale])) {
        return Object.keys(byLocale).some((code) => code !== locale && !isMissing(byLocale[code])) ? 'untranslated' : null;
    }

    return outdatedSource(values, translated, locale) === undefined ? 'up_to_date' : 'outdated';
}

/**
 * What an outdated value shows: the value it was translated from as that locale has it now. Undefined when the value
 * isn't outdated: it's missing, it has no source, its source has no value any more, or its source hasn't changed.
 */
export function outdatedSource(values: JsonValue | undefined, translated: Readonly<Record<string, TranslationSource>> | undefined, locale: string): LocaleValue | undefined {
    const byLocale = localeMap(values);
    const source = translated?.[locale];
    const value = source === undefined ? undefined : byLocale[source.from];
    if (source === undefined || value === undefined || isMissing(byLocale[locale]) || isMissing(value)) {
        return undefined;
    }

    return fingerprint(value) === source.fingerprint ? undefined : { locale: source.from, value };
}

/**
 * Where a translatable value typed in the edited locale is translated from once it's typed: the locale whose value the
 * fallback placeholder showed, when it's typed over the placeholder or copied from it, with the fingerprint of that
 * value. A value that had a source keeps it, with the fingerprint of the source's value now, because updating a value
 * brings it up to date. A value typed without a source, and an emptied one, have none.
 *
 * @param values what the field stores before the edit: a map from locale to value
 * @param current where the edited locale's value was translated from before the edit
 * @param value the value typed, or null when it's emptied
 * @param chain the edited locale, then the locales it falls back through
 */
export function sourceOfTyped(values: JsonValue | undefined, current: TranslationSource | undefined, value: JsonValue | null, chain: readonly string[]): TranslationSource | null {
    if (isMissing(value ?? undefined)) {
        return null;
    }

    if (current !== undefined) {
        return upToDate(values, current) ?? current;
    }

    const typedOver = chain[0] === undefined || isMissing(localeMap(values)[chain[0]]);
    const fallback = typedOver ? fallbackValue(values, chain) : undefined;

    return fallback === undefined ? null : { from: fallback.locale, fingerprint: fingerprint(fallback.value) };
}

/**
 * Where a value was translated from with the fingerprint its source's value has now, which "Mark as up to date"
 * records. Undefined when its source has no value.
 */
export function upToDate(values: JsonValue | undefined, source: TranslationSource): TranslationSource | undefined {
    const value = localeMap(values)[source.from];

    return value === undefined || isMissing(value) ? undefined : { from: source.from, fingerprint: fingerprint(value) };
}

/** An entry field's values, such as the title, as a map from locale to value, like a translatable block field's. */
export function entryValues(entry: Pick<Entry, 'translations'>, field: string): JsonObject {
    return Object.fromEntries(Object.entries(entry.translations).map(([locale, translation]) => [locale, translation.fields[field] ?? null]));
}

/** Where each locale's value of an entry field was translated from. */
export function entryTranslated(entry: Pick<Entry, 'translations'>, field: string): Record<string, TranslationSource> {
    return Object.fromEntries(
        Object.entries(entry.translations).flatMap(([locale, translation]) => {
            const source = translation.translated[field];

            return source === undefined ? [] : [[locale, source]];
        }),
    );
}

/**
 * How many of the entry's translatable values are untranslated and outdated in the locale: its own fields, such as the
 * title, and its blocks' fields. The layout's come with the layouts.
 */
export function localeCounts(entry: Entry, locale: string, isTranslatable: TranslatableLookup): LocaleCounts {
    const counts: LocaleCounts = { untranslated: 0, outdated: 0 };
    const count = (state: ValueState | null): void => {
        if (state === 'untranslated') {
            counts.untranslated++;
        } else if (state === 'outdated') {
            counts.outdated++;
        }
    };

    const entryFields = new Set(Object.values(entry.translations).flatMap((translation) => Object.keys(translation.fields)));
    for (const field of entryFields) {
        count(valueState(entryValues(entry, field), entryTranslated(entry, field), locale));
    }

    const countBlocks = (blocks: readonly StoredBlock[]): void => {
        for (const block of blocks) {
            for (const field of Object.keys(block.fields)) {
                if (isTranslatable(block.type, field)) {
                    count(valueState(block.fields[field], block.translated[field], locale));
                }
            }

            countBlocks(block.children);
        }
    };
    countBlocks(entry.tree.blocks);

    return counts;
}

function localeMap(values: JsonValue | undefined): JsonObject {
    return isJsonObject(values) ? values : {};
}
