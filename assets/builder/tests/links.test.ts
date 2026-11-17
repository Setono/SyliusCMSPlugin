import { describe, expect, it } from 'vitest';
import { linkShape, offeredTargets, readLink, writeLink, type LinkTargetDefinition } from '../src/links';
import type { FieldSchema } from '../src/types';

const linkTargets: LinkTargetDefinition[] = [
    { code: 'entry', label: 'Entry', translatableRef: false },
    { code: 'product', label: 'Product', translatableRef: false },
    { code: 'url', label: 'URL', translatableRef: true },
];

function schema(options: Record<string, unknown>): FieldSchema {
    return {
        type: 'object',
        'x-cms-type': 'link',
        'x-cms-label': 'Link',
        'x-cms-help': null,
        'x-cms-required': true,
        'x-cms-translatable': options.translatable === true,
        'x-cms-options': options as FieldSchema['x-cms-options'],
    };
}

describe('the shape of a Link field', () => {
    it('should keep the label and a translatable ref per locale unless the field says otherwise', () => {
        expect(linkShape(schema({ targets: null, translatable: null }))).toBe('per_locale');
        expect(linkShape(schema({ targets: null, translatable: false }))).toBe('shared');
        expect(linkShape(schema({ targets: null, translatable: true }))).toBe('whole');
    });

    it('should offer the targets the field links to, in its order, and every target when it names none', () => {
        expect(offeredTargets(schema({ targets: ['url', 'media', 'product'], translatable: null }), linkTargets).map(({ code }) => code)).toEqual(['url', 'product']);
        expect(offeredTargets(schema({ targets: null, translatable: null }), linkTargets).map(({ code }) => code)).toEqual(['entry', 'product', 'url']);
    });
});

describe('reading a link in the edited locale', () => {
    it('should read the label of the edited locale and what it falls back to', () => {
        const link = readLink({ target: 'product', ref: 'T_SHIRT', anchor: 'sizes', label: { en_US: 'Shop now' } }, 'per_locale', ['de_DE', 'en_US'], 'entry');

        expect(link).toEqual({ target: 'product', ref: 'T_SHIRT', anchor: 'sizes', label: '', refFallback: undefined, labelFallback: 'Shop now' });
    });

    it('should read a URL of the edited locale and what it falls back to', () => {
        const link = readLink({ target: 'url', ref: { en_US: 'https://example.com/en', de_DE: 'https://example.com/de' }, anchor: null, label: {} }, 'per_locale', ['de_DE', 'en_US'], 'entry');

        expect([link.ref, link.refFallback]).toEqual(['https://example.com/de', 'https://example.com/en']);
    });

    it('should read one label and ref for every locale', () => {
        const link = readLink({ target: 'url', ref: 'tel:+4512345678', anchor: null, label: 'Call us' }, 'shared', ['de_DE', 'en_US'], 'entry');

        expect(link).toEqual({ target: 'url', ref: 'tel:+4512345678', anchor: '', label: 'Call us', refFallback: undefined, labelFallback: undefined });
    });

    it('should read no link as a link to the first target the field offers', () => {
        expect(readLink(undefined, 'per_locale', ['en_US'], 'entry')).toEqual({ target: 'entry', ref: '', anchor: '', label: '', refFallback: undefined, labelFallback: undefined });
    });
});

describe('changing a link in the edited locale', () => {
    it('should start a link with the target picked and keep its anchor and label', () => {
        expect(writeLink(undefined, 'per_locale', 'en_US', { target: 'product' }, linkTargets, 'entry')).toEqual({ target: 'product', ref: '', anchor: null, label: {} });
        expect(writeLink({ target: 'entry', ref: 'a-uuid', anchor: 'top', label: { en_US: 'Read more' } }, 'per_locale', 'en_US', { target: 'url' }, linkTargets, 'entry')).toEqual({
            target: 'url',
            ref: {},
            anchor: 'top',
            label: { en_US: 'Read more' },
        });
    });

    it('should set what it links to, per locale for a URL', () => {
        expect(writeLink({ target: 'product', ref: '', anchor: null, label: {} }, 'per_locale', 'en_US', { ref: 'T_SHIRT' }, linkTargets, 'entry')).toEqual({ target: 'product', ref: 'T_SHIRT', anchor: null, label: {} });
        expect(writeLink({ target: 'url', ref: { en_US: 'https://example.com/en' }, anchor: null, label: {} }, 'per_locale', 'de_DE', { ref: 'https://example.com/de' }, linkTargets, 'entry')).toEqual({
            target: 'url',
            ref: { en_US: 'https://example.com/en', de_DE: 'https://example.com/de' },
            anchor: null,
            label: {},
        });
        expect(writeLink({ target: 'url', ref: 'https://example.com', anchor: null, label: null }, 'shared', 'de_DE', { ref: 'mailto:shop@example.com' }, linkTargets, 'entry').ref).toBe('mailto:shop@example.com');
    });

    it('should set the label of the edited locale, and take an emptied one out', () => {
        const link = { target: 'product', ref: 'T_SHIRT', anchor: null, label: { en_US: 'Shop now', de_DE: 'Jetzt kaufen' } };

        expect(writeLink(link, 'per_locale', 'de_DE', { label: '' }, linkTargets, 'entry').label).toEqual({ en_US: 'Shop now' });
        expect(writeLink(link, 'per_locale', 'da_DK', { label: 'Køb nu' }, linkTargets, 'entry').label).toEqual({ en_US: 'Shop now', de_DE: 'Jetzt kaufen', da_DK: 'Køb nu' });
        expect(writeLink({ target: 'product', ref: 'T_SHIRT', anchor: null, label: 'Shop' }, 'shared', 'de_DE', { label: '' }, linkTargets, 'entry').label).toBeNull();
    });

    it('should keep an anchor without its hash, and none for an empty one', () => {
        const link = { target: 'product', ref: 'T_SHIRT', anchor: null, label: {} };

        expect(writeLink(link, 'per_locale', 'en_US', { anchor: '#sizes' }, linkTargets, 'entry').anchor).toBe('sizes');
        expect(writeLink(link, 'per_locale', 'en_US', { anchor: ' ' }, linkTargets, 'entry').anchor).toBeNull();
    });
});
