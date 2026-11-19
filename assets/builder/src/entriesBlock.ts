/**
 * The Entries block lists entries through their content type's card for its display, and offers the displays the
 * listed content type has cards for, which the card_displays endpoint tells for what its entries field holds. See "The
 * Entries block" in docs/spec/collections.md. Pure functions, with no React.
 */
import type { FieldSchema } from './types';
import { isJsonObject } from './values';

/** The Entries block's type code. */
export const ENTRIES_BLOCK = 'entries';

/** Its list of entries. */
export const ENTRIES_FIELD = 'entries';

/** How its entries show: one of the displays their content type has cards for. */
export const DISPLAY_FIELD = 'display';

/**
 * The display field's schema with only the offered displays as its choices, in their own order, and the one the block
 * holds, or the default, so the select still shows it. Unchanged while it's not known what's offered.
 */
export function offeredDisplayChoices(schema: FieldSchema, offered: readonly string[] | null, current: string | undefined): FieldSchema {
    const options = schema['x-cms-options'];
    if (offered === null || !isJsonObject(options) || !isJsonObject(options.choices)) {
        return schema;
    }

    const held = current ?? (typeof schema.default === 'string' ? schema.default : undefined);
    const choices = Object.fromEntries(Object.entries(options.choices).filter(([display]) => offered.includes(display) || display === held));

    return { ...schema, 'x-cms-options': { ...options, choices } };
}
