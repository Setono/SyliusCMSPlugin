/**
 * The translation state of an entry's values: which are untranslated or outdated in a locale, and where a value typed
 * in a locale is translated from. The same rules as the server's (src/Translation/TranslatableValues.php), which the
 * entry lists and its overview page count with. Pure functions, with no React and no I/O. See "Completeness and
 * outdated flags" in docs/spec/localization.md and "Translation state" in docs/spec/content-model.md
 */
import { hiddenInPreview } from './conditions';
import { isMissing } from './fallback';
import { fingerprint } from './fingerprint';
import { keepsPartsPerLocale, localeParts } from './links';
import type { Entry, FieldSchema, JsonObject, JsonValue, LayoutDefinition, StoredBlock, TranslationSource } from './types';
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

/** The schema of a block type's field, or undefined when the code doesn't have it. */
export type FieldSchemaLookup = (blockType: string, field: string) => FieldSchema | undefined;

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
 * Where a translatable value typed in the edited locale is translated from once it's typed over a missing value: the
 * locale an untranslated value is translated from, as the server finds it, with the fingerprint of its value. That's
 * the one whose value the fallback placeholder showed, or else the first locale by code with a value. A value that had
 * a source keeps it, with the fingerprint of the source's value now, because updating a value brings it up to date. A
 * value typed while no other locale has one, and an emptied one, have none.
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
    const source = typedOver ? untranslatedSource(values, chain) : undefined;

    return source === undefined ? null : { from: source.locale, fingerprint: fingerprint(source.value) };
}

/**
 * The locale a value missing in the first locale of the chain is translated from: the first the chain reaches that has
 * a value, or else the first by code that has one, as the server's TranslatableValues::untranslatedSource(). Undefined
 * when no other locale has a value.
 */
function untranslatedSource(values: JsonValue | undefined, chain: readonly string[]): LocaleValue | undefined {
    const byLocale = localeMap(values);
    const others = Object.keys(byLocale)
        .filter((locale) => locale !== chain[0] && !isMissing(byLocale[locale]))
        .sort();
    const locale = chain.slice(1).find((chained) => others.includes(chained)) ?? others[0];
    const value = locale === undefined ? undefined : byLocale[locale];

    return locale === undefined || value === undefined ? undefined : { locale, value };
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
 * title, its layout's fields and its blocks' fields. A block a locale condition hides in the locale is left out, with
 * everything inside it, because nobody reads it there.
 *
 * @param schemaOf the schemas of the block types' fields
 * @param layout the definition of the layout the tree is in, or undefined when it was removed or disabled, so its values aren't shown
 */
export function localeCounts(entry: Entry, locale: string, schemaOf: FieldSchemaLookup, layout: LayoutDefinition | undefined): LocaleCounts {
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

    const stored = entry.tree.layout;
    if (stored !== null && layout !== undefined) {
        for (const { name, schema } of layout.fields) {
            const values = localeValues(schema, stored.fields[name]);
            if (values !== null) {
                count(valueState(values, stored.translated[name], locale));
            }
        }
    }

    const countBlocks = (blocks: readonly StoredBlock[]): void => {
        for (const block of blocks) {
            // Without a channel, only locale conditions count
            if (hiddenInPreview(block, null, locale)) {
                continue;
            }

            for (const field of Object.keys(block.fields)) {
                const values = localeValues(schemaOf(block.type, field), block.fields[field]);
                if (values !== null) {
                    count(valueState(values, block.translated[field], locale));
                }
            }

            countBlocks(block.children);
        }
    };
    countBlocks(entry.tree.blocks);

    return counts;
}

/**
 * A field's values by locale, as the translation state reads them: a translatable field's own, or the label and URL a
 * Link of the default shape keeps per locale inside its one value. Null for a shared field, or one the code doesn't have,
 * which has nothing to translate.
 */
export function localeValues(schema: FieldSchema | undefined, stored: JsonValue | undefined): JsonValue | undefined | null {
    if (schema === undefined) {
        return null;
    }

    if (keepsPartsPerLocale(schema)) {
        return localeParts(stored);
    }

    return schema['x-cms-translatable'] === true ? stored : null;
}

function localeMap(values: JsonValue | undefined): JsonObject {
    return isJsonObject(values) ? values : {};
}
