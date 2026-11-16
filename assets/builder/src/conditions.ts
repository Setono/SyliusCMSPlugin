/**
 * Visibility conditions as the builder edits and reads them. Pure functions, with no React and no I/O.
 * See "Visibility conditions" in docs/spec/targeting.md
 */
import type { JsonValue, StoredBlock, VisibilityCondition } from './types';
import { isMissing } from './fallback';

/** The parameter of each condition kind the preview's channel and locale decide on its own. */
const previewed: Readonly<Record<string, { param: string; of: 'channel' | 'locale' }>> = {
    channel: { param: 'channels', of: 'channel' },
    locale: { param: 'locales', of: 'locale' },
};

/**
 * Whether a channel or locale condition among the block's hides it in the preview's channel and locale, as the server
 * decides it: "is one of" matches when the list has the value, and "is not one of" when it hasn't. Other kinds read
 * what the builder doesn't know, such as the time or the customer, so they never count here.
 */
export function hiddenInPreview(block: Pick<StoredBlock, 'conditions'>, channel: string | null, locale: string): boolean {
    return block.conditions.some((condition) => {
        const decided = previewed[condition.kind];
        const current = decided?.of === 'channel' ? channel : locale;
        if (decided === undefined || current === null) {
            return false;
        }

        const values = condition.params[decided.param];
        const matches = Array.isArray(values) && values.includes(current);

        return matches === condition.negate;
    });
}

/** A new condition of the kind: "is one of", with no values yet. */
export function newCondition(kind: string): VisibilityCondition {
    return { kind, negate: false, params: {} };
}

/** The condition with a parameter's value set, or taken away when it's null, '' or []. */
export function withParam(condition: VisibilityCondition, name: string, value: JsonValue | null): VisibilityCondition {
    const { [name]: _replaced, ...params } = condition.params;

    return { ...condition, params: value === null || isMissing(value) ? params : { ...params, [name]: value } };
}
