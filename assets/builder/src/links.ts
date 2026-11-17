/**
 * Links in the builder: the value a Link field stores, in the shape the field's translatable picks, read and written
 * for the locale being edited. Pure functions, with no React and no I/O. See "Links" in docs/spec/content-model.md
 */
import { fallbackValue } from './fallback';
import type { FieldSchema, JsonObject, JsonValue } from './types';
import { isJsonObject } from './values';

/** A link target, as the registry has it: what links lead to, such as an entry, a product or a URL. */
export interface LinkTargetDefinition {
    code: string;
    label: string;
    /** Whether a link keeps its ref per locale, as a URL does: it's typed, where the others are picked with the search under their code. */
    translatableRef: boolean;
}

/**
 * How a Link field keeps its value, which its translatable picks: `per_locale` (null) keeps the label, and the ref of a
 * target whose ref is translatable, per locale inside the one value; `shared` (false) keeps one link for every locale;
 * and `whole` (true) keeps a whole link per locale, of which the control gets the edited locale's.
 */
export type LinkShape = 'per_locale' | 'shared' | 'whole';

export function linkShape(schema: FieldSchema): LinkShape {
    const translatable = schema['x-cms-options']?.translatable;

    return translatable === true ? 'whole' : translatable === false ? 'shared' : 'per_locale';
}

/**
 * Whether a field is a Link that keeps its label, and the ref of a target whose ref is translatable, per locale inside
 * its one shared value, as a Link field of the default shape does. Those are translated, so it carries no "All
 * languages" badge, and the translation state counts them.
 */
export function keepsPartsPerLocale(schema: FieldSchema): boolean {
    return schema['x-cms-type'] === 'link' && linkShape(schema) === 'per_locale';
}

/**
 * The label and URL a link of the default shape keeps per locale, by locale: {label?, ref?} with what each locale has
 * of them, leaving out the locales that have neither, as the server's LinkLocaleParts::byLocale() gives them.
 */
export function localeParts(value: JsonValue | undefined): JsonObject {
    const link = isJsonObject(value) ? value : {};
    const parts: Record<string, JsonObject> = {};
    for (const part of ['label', 'ref']) {
        const map = link[part];
        if (!isJsonObject(map)) {
            continue;
        }

        for (const [locale, localized] of Object.entries(map)) {
            if (typeof localized === 'string' && localized !== '') {
                parts[locale] = { ...parts[locale], [part]: localized };
            }
        }
    }

    return parts;
}

/** The link targets the field links to, in its order. Those the registry doesn't have are left out. */
export function offeredTargets(schema: FieldSchema, linkTargets: readonly LinkTargetDefinition[]): LinkTargetDefinition[] {
    const codes = schema['x-cms-options']?.targets;
    if (!Array.isArray(codes)) {
        return [...linkTargets];
    }

    return codes.flatMap((code) => linkTargets.filter((target) => target.code === code));
}

/** A link as the control shows it in the edited locale: '' for what it doesn't hold. */
export interface EditedLink {
    target: string;
    ref: string;
    anchor: string;
    label: string;
    /** What an empty ref falls back to from another locale, for a ref kept per locale. */
    refFallback: string | undefined;
    /** What an empty label falls back to from another locale, for a label kept per locale. */
    labelFallback: string | undefined;
}

/**
 * The link in the edited locale. A field without one links to the first target it offers.
 *
 * @param chain the edited locale first, then the locales it falls back through
 */
export function readLink(value: JsonValue | undefined, shape: LinkShape, chain: readonly string[], firstTarget: string): EditedLink {
    const link = isJsonObject(value) ? value : {};
    const [ref, refFallback] = localized(link.ref, shape, chain);
    const [label, labelFallback] = localized(link.label, shape, chain);

    return {
        target: typeof link.target === 'string' ? link.target : firstTarget,
        ref,
        anchor: typeof link.anchor === 'string' ? link.anchor : '',
        label,
        refFallback,
        labelFallback,
    };
}

/** A change the editor makes to a link in the edited locale. */
export type LinkChange = { target: string } | { ref: string } | { anchor: string } | { label: string };

/**
 * The value with the change made in the edited locale. Another target starts with an empty ref, kept per locale for a
 * target whose ref is translatable, and keeps the anchor and label. An anchor is kept without its #.
 */
export function writeLink(
    value: JsonValue | undefined,
    shape: LinkShape,
    locale: string,
    change: LinkChange,
    linkTargets: readonly LinkTargetDefinition[],
    firstTarget: string,
): JsonObject {
    const link = isJsonObject(value) ? value : {};
    const target = typeof link.target === 'string' ? link.target : firstTarget;
    const refPerLocale = (code: string): boolean => shape === 'per_locale' && linkTargets.some((definition) => definition.code === code && definition.translatableRef);
    const written: JsonObject = {
        target,
        ref: link.ref ?? (refPerLocale(target) ? {} : ''),
        anchor: typeof link.anchor === 'string' && link.anchor !== '' ? link.anchor : null,
        label: link.label ?? (shape === 'per_locale' ? {} : null),
    };

    if ('target' in change) {
        return { ...written, target: change.target, ref: refPerLocale(change.target) ? {} : '' };
    }

    if ('ref' in change) {
        return { ...written, ref: refPerLocale(target) ? inLocale(written.ref, locale, change.ref) : change.ref };
    }

    if ('anchor' in change) {
        const anchor = change.anchor.trim().replace(/^#/, '');

        return { ...written, anchor: anchor === '' ? null : anchor };
    }

    return { ...written, label: shape === 'per_locale' ? inLocale(written.label, locale, change.label) : change.label === '' ? null : change.label };
}

/** A value kept per locale, or one shared string, in the edited locale, and what it falls back to when it's empty there. */
function localized(value: JsonValue | undefined, shape: LinkShape, chain: readonly string[]): [string, string | undefined] {
    if (typeof value === 'string') {
        return [value, undefined];
    }

    if (shape !== 'per_locale' || !isJsonObject(value)) {
        return ['', undefined];
    }

    const own = value[chain[0] ?? ''];
    const fallback = fallbackValue(value, chain)?.value;

    return [typeof own === 'string' ? own : '', typeof fallback === 'string' ? fallback : undefined];
}

/** The locale map with the locale's value set, or taken out when it's empty. */
function inLocale(map: JsonValue | undefined, locale: string, value: string): JsonObject {
    const { [locale]: _, ...others } = isJsonObject(map) ? map : {};

    return value === '' ? others : { ...others, [locale]: value };
}
