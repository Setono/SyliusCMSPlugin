/**
 * The locales the builder edits an entry in, one at a time, and what it knows about them. Pure functions, with no React
 * and no I/O. See "In the builder" in docs/spec/localization.md
 */
import type { Channel, Locale } from './config';
import { fallbackValue, type FieldFallback } from './fallback';
import { outdatedSource, type OutdatedValue } from './translationState';
import type { Entry, JsonValue, TranslationSource } from './types';

/**
 * The locales the locale switcher offers: those of the entry's channels, in the order the store lists them, and the
 * edited locale, when none of the entry's channels has it.
 */
export function offeredLocales(locales: readonly Locale[], channels: readonly Channel[], entryChannels: readonly string[], edited: string): Locale[] {
    const codes = new Set(channels.filter(({ code }) => entryChannels.includes(code)).flatMap((channel) => channel.locales ?? []));
    const offered = locales.filter(({ code }) => codes.has(code));

    return offered.some(({ code }) => code === edited) ? offered : [...offered, localeOf(locales, edited)];
}

/** What the builder knows about a locale. A locale the store's channels don't have is named by its code and falls back nowhere. */
export function localeOf(locales: readonly Locale[], code: string): Locale {
    return locales.find((locale) => locale.code === code) ?? { code, name: code, fallbackChain: [code] };
}

/** Whether the entry has a translation in the locale, which it has once that translation has a title. */
export function hasTranslation(entry: Entry, locale: string): boolean {
    const title = entry.translations[locale]?.fields.title;

    return typeof title === 'string' && title !== '';
}

/**
 * The locale of the translation a new translation in the locale starts from: the first one its fallback chain reaches
 * that the entry has, or else the first one the entry has. Undefined when it has none.
 */
export function sourceLocale(entry: Entry, locale: Locale): string | undefined {
    const translated = Object.keys(entry.translations).filter((code) => code !== locale.code && hasTranslation(entry, code));

    return locale.fallbackChain.find((code) => translated.includes(code)) ?? translated.sort()[0];
}

/**
 * What a translatable field shows while it's empty in the edited locale: the value it falls back to, with the name of
 * the locale it's from, or undefined when it falls back to nothing.
 *
 * @param values what the field stores: a map from locale to value
 */
export function fieldFallback(locales: readonly Locale[], values: JsonValue | undefined, edited: string): FieldFallback | undefined {
    const found = fallbackValue(values, localeOf(locales, edited).fallbackChain);

    return found === undefined ? undefined : { value: found.value, localeName: localeOf(locales, found.locale).name };
}

/**
 * What a translatable field shows while its value in the edited locale is outdated: the value it was translated from,
 * as that locale has it now, with the name of the locale. Undefined when the value isn't outdated.
 *
 * @param values what the field stores: a map from locale to value
 * @param translated where each locale's value was translated from
 */
export function outdatedValue(
    locales: readonly Locale[],
    values: JsonValue | undefined,
    translated: Readonly<Record<string, TranslationSource>> | undefined,
    edited: string,
    onMarkUpToDate: () => void,
): OutdatedValue | undefined {
    const source = outdatedSource(values, translated, edited);

    return source === undefined ? undefined : { value: source.value, localeName: localeOf(locales, source.locale).name, onMarkUpToDate };
}
