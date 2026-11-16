import { describe, expect, it } from 'vitest';
import { isRemovedLayout, layoutOf, pickableLayouts, renderedLayout, switchLayout } from '../src/layouts';
import type { LayoutDefinition, StoredLayout } from '../src/types';
import { landing, standard } from './fixtures';

const source = { from: 'en_US', fingerprint: 'a'.repeat(64) };

/** A layout with a field of each kind Landing has, under names that test the carrying over. */
const promo: LayoutDefinition = {
    ...landing,
    code: 'promo',
    version: 3,
    fields: [
        // Same name and field type as Landing's: carries over
        { name: 'tagline', schema: { ...landing.fields[0]!.schema } },
        // Same name as Landing's, but a text field and not a toggle: dropped
        { name: 'transparentHeader', schema: { ...landing.fields[0]!.schema } },
        // Same name and field type as Standard's, but translatable: dropped
        { name: 'showTitle', schema: { ...standard.fields[0]!.schema, 'x-cms-translatable': true } },
    ],
};

describe('switching layout', () => {
    it('should carry values over to the fields of the new layout with the same name and field type, with their translation state, and drop the rest', () => {
        const stored: StoredLayout = {
            code: 'landing',
            version: 2,
            fields: { tagline: { en_US: 'Big news', da_DK: 'Nyhed' }, transparentHeader: true },
            translated: { tagline: { da_DK: source } },
        };

        expect(switchLayout(stored, landing, promo)).toEqual({
            code: 'promo',
            version: 3,
            fields: { tagline: { en_US: 'Big news', da_DK: 'Nyhed' } },
            translated: { tagline: { da_DK: source } },
        });
    });

    it('should drop a value whose field turned translatable or shared', () => {
        expect(switchLayout({ code: 'standard', version: 1, fields: { showTitle: false }, translated: {} }, standard, promo)).toEqual({ code: 'promo', version: 3, fields: {}, translated: {} });
    });

    it('should carry a shared value over without translation state', () => {
        const wide: LayoutDefinition = { ...standard, code: 'wide' };

        expect(switchLayout({ code: 'standard', version: 1, fields: { showTitle: false }, translated: {} }, standard, wide)).toEqual({ code: 'wide', version: 1, fields: { showTitle: false }, translated: {} });
    });

    it('should drop every value of a layout the code no longer has, whose field types are unknown', () => {
        expect(switchLayout({ code: 'gone', version: 1, fields: { tagline: { en_US: 'Big news' } }, translated: {} }, undefined, landing)).toEqual({ code: 'landing', version: 2, fields: {}, translated: {} });
    });

    it('should start a tree without a layout in the new one with no values', () => {
        expect(switchLayout(null, undefined, standard)).toEqual({ code: 'standard', version: 1, fields: {}, translated: {} });
    });

    it('should not share the values with the layout it switched from', () => {
        const stored: StoredLayout = { code: 'landing', version: 2, fields: { tagline: { en_US: 'Big news' } }, translated: {} };

        const switched = switchLayout(stored, landing, promo);

        expect(switched.fields['tagline']).not.toBe(stored.fields['tagline']);
    });
});

describe('the layout an entry renders in', () => {
    const disabled: LayoutDefinition = { ...landing, code: 'disabled', disabled: true };
    const layouts = [standard, landing, disabled];
    const stored = (code: string): StoredLayout => ({ code, version: 1, fields: {}, translated: {} });

    it('should be its own, unless the code no longer has it or it is disabled, and then the default', () => {
        expect(renderedLayout(layouts, stored('landing'))).toBe(landing);
        expect(renderedLayout(layouts, stored('gone'))).toBe(standard);
        expect(renderedLayout(layouts, stored('disabled'))).toBe(standard);
        expect(renderedLayout(layouts, null)).toBe(standard);
        expect(renderedLayout([], null)).toBeUndefined();
    });

    it('should be flagged when it was removed or disabled', () => {
        expect(isRemovedLayout(layouts, stored('landing'))).toBe(false);
        expect(isRemovedLayout(layouts, stored('gone'))).toBe(true);
        expect(isRemovedLayout(layouts, stored('disabled'))).toBe(true);
        expect(isRemovedLayout(layouts, null)).toBe(false);
        expect(isRemovedLayout([], stored('standard'))).toBe(false);
    });

    it('should find the definition of a stored layout, a disabled one too', () => {
        expect(layoutOf(layouts, stored('disabled'))).toBe(disabled);
        expect(layoutOf(layouts, stored('gone'))).toBeUndefined();
        expect(layoutOf(layouts, null)).toBeUndefined();
    });

    it('should offer every layout but the disabled ones to pick', () => {
        expect(pickableLayouts(layouts)).toEqual([standard, landing]);
    });
});
