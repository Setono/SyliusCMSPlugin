import { describe, expect, it } from 'vitest';
import { applyChanges, mergeChanges, type Change } from '../src/changeSet';
import type { Locale } from '../src/config';
import { fingerprint } from '../src/fingerprint';
import { createBuilderStore } from '../src/store';
import { entryTranslated, entryValues, localeCounts, outdatedSource, sourceOfTyped, upToDate, valueState, type FieldSchemaLookup } from '../src/translationState';
import type { Entry, EntryStatus, FieldSchema, JsonValue, StoredBlock, TranslationSource } from '../src/types';
import { block, blockOf, blockTypes, entry, page, standard } from './fixtures';

const locales: Locale[] = [
    { code: 'en_US', name: 'English (United States)', fallbackChain: ['en_US'] },
    { code: 'da_DK', name: 'Danish (Denmark)', fallbackChain: ['da_DK', 'en_US'] },
    { code: 'de_CH', name: 'German (Switzerland)', fallbackChain: ['de_CH', 'de_DE', 'en_US'] },
];

const from = (locale: string, value: JsonValue): TranslationSource => ({ from: locale, fingerprint: fingerprint(value) });

const document = (text: string): JsonValue => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text }] }] });

const isTranslatable = (blockType: string, field: string): boolean => (blockType === 'heading' && field === 'text') || (blockType === 'accordion_item' && field === 'title');

const text: FieldSchema = { type: 'string', 'x-cms-type': 'text', 'x-cms-label': 'Text', 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': true };

function link(translatable: boolean | null): FieldSchema {
    return {
        type: 'object',
        'x-cms-type': 'link',
        'x-cms-label': 'Link',
        'x-cms-help': null,
        'x-cms-required': true,
        'x-cms-translatable': translatable === true,
        'x-cms-options': { targets: null, translatable },
    };
}

/** The headings' and accordion items' translatable texts, and a button's link of each shape. */
const schemaOf: FieldSchemaLookup = (blockType, field) => {
    if (blockType === 'button') {
        return field === 'link' ? link(null) : field === 'shared' ? link(false) : undefined;
    }

    return isTranslatable(blockType, field) ? text : undefined;
};

/** A heading with its text in English and Danish, the Danish translated from the English given. */
function translatedHeading(key: string, english: string, translatedFrom: string): StoredBlock {
    return { ...block(key, { text: { en_US: english, da_DK: 'Hej' }, level: 'h2' }), translated: { text: { da_DK: from('en_US', translatedFrom) } } };
}

/** An entry with an English and a Danish translation, the Danish title translated from the English title given. */
function danish(blocks: StoredBlock[], titleFrom: string | null = 'About us'): Entry {
    const base = entry(blocks);

    return {
        ...base,
        translations: {
            ...base.translations,
            da_DK: { shown: true, fields: { slug: 'om-os', title: 'Om os' }, translated: titleFrom === null ? {} : { title: from('en_US', titleFrom) } },
        },
    };
}

function storeOn(edited: Entry, locale = 'da_DK', status: EntryStatus = 'never_published') {
    return createBuilderStore({ locale, locales, blockTypes, contentTypes: [page], entry: edited, status });
}

describe('the state of a translatable value in a locale', () => {
    it('should be untranslated when it is missing or empty there while another locale has one', () => {
        expect(valueState({ en_US: 'Hello' }, {}, 'da_DK')).toBe('untranslated');
        expect(valueState({ en_US: 'Hello', da_DK: '' }, {}, 'da_DK')).toBe('untranslated');
        expect(valueState({ en_US: document('Hello'), da_DK: { type: 'doc', content: [{ type: 'paragraph' }] } }, {}, 'da_DK')).toBe('untranslated');
    });

    it('should be nothing when no locale has a value', () => {
        expect(valueState(undefined, undefined, 'da_DK')).toBeNull();
        expect(valueState({ en_US: '', da_DK: null }, {}, 'da_DK')).toBeNull();
        expect(valueState('a shared value', {}, 'da_DK')).toBeNull();
    });

    it('should be outdated when the value it was translated from has changed since', () => {
        expect(valueState({ en_US: 'Hello there', da_DK: 'Hej' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toBe('outdated');
        expect(outdatedSource({ en_US: 'Hello there', da_DK: 'Hej' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toEqual({ locale: 'en_US', value: 'Hello there' });
    });

    it('should be up to date when the value it was translated from is unchanged, or it has no source, or its source has no value', () => {
        expect(valueState({ en_US: 'Hello', da_DK: 'Hej' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toBe('up_to_date');
        expect(valueState({ en_US: 'Hello', da_DK: 'Hej' }, {}, 'da_DK')).toBe('up_to_date');
        expect(valueState({ en_US: '', da_DK: 'Hej' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toBe('up_to_date');
        expect(valueState({ da_DK: 'Hej' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toBe('up_to_date');
        expect(outdatedSource({ en_US: 'Hello', da_DK: 'Hej' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toBeUndefined();
    });

    it("should not flag an empty value as outdated, whatever its source's state", () => {
        expect(outdatedSource({ en_US: 'Hello there' }, { da_DK: from('en_US', 'Hello') }, 'da_DK')).toBeUndefined();
    });

    it('should compare rich text whatever the order of its keys', () => {
        const english = { content: [{ content: [{ text: 'Hello', type: 'text' }], type: 'paragraph' }], type: 'doc' };

        expect(valueState({ en_US: english, da_DK: document('Hej') }, { da_DK: from('en_US', document('Hello')) }, 'da_DK')).toBe('up_to_date');
    });
});

describe('where a typed value is translated from', () => {
    const chain = ['da_DK', 'en_US'];

    it('should be the locale of the fallback value it was typed over or copied from, with its fingerprint', () => {
        expect(sourceOfTyped({ en_US: 'Hello' }, undefined, 'Hej', chain)).toEqual(from('en_US', 'Hello'));
        expect(sourceOfTyped({ en_US: 'Hello', da_DK: '' }, undefined, 'Hello', chain)).toEqual(from('en_US', 'Hello'));
    });

    // As the server's untranslatedSource() finds it, so the builder and the admin agree on what a value is translated from
    it("should be the first locale by code with a value when the fallback chain reaches none, as the server's", () => {
        expect(sourceOfTyped({ sv_SE: 'Hej', de_DE: 'Hallo', en_US: '' }, undefined, 'Hej', chain)).toEqual(from('de_DE', 'Hallo'));
        expect(sourceOfTyped({ de_DE: 'Hallo', en_US: 'Hello' }, undefined, 'Hej', chain)).toEqual(from('en_US', 'Hello'));
    });

    it('should be nothing for a value typed while no other locale has one, or over a value the locale had, or emptied', () => {
        expect(sourceOfTyped({ en_US: '' }, undefined, 'Hej', chain)).toBeNull();
        expect(sourceOfTyped(undefined, undefined, 'Hej', chain)).toBeNull();
        expect(sourceOfTyped({ en_US: 'Hello', da_DK: 'Hej' }, undefined, 'Hej!', chain)).toBeNull();
        expect(sourceOfTyped({ en_US: 'Hello', da_DK: 'Hej' }, from('en_US', 'Hello'), null, chain)).toBeNull();
        expect(sourceOfTyped({ en_US: 'Hello', da_DK: 'Hej' }, from('en_US', 'Hello'), { type: 'doc', content: [] }, chain)).toBeNull();
    });

    it("should keep a value's source, brought up to date, when the value is updated", () => {
        expect(sourceOfTyped({ en_US: 'Hello there', da_DK: 'Hej' }, from('en_US', 'Hello'), 'Hej der', chain)).toEqual(from('en_US', 'Hello there'));
        expect(sourceOfTyped({ de_DE: 'Hallo', da_DK: 'Hej' }, from('de_DE', 'Hallo'), 'Hej der', chain)).toEqual(from('de_DE', 'Hallo'));
    });

    it('should keep the source as it is when the source has no value any more', () => {
        expect(sourceOfTyped({ da_DK: 'Hej' }, from('en_US', 'Hello'), 'Hej der', chain)).toEqual(from('en_US', 'Hello'));
    });

    it('should bring a source up to date only while it has a value', () => {
        expect(upToDate({ en_US: 'Hello there' }, from('en_US', 'Hello'))).toEqual(from('en_US', 'Hello there'));
        expect(upToDate({ en_US: '' }, from('en_US', 'Hello'))).toBeUndefined();
        expect(upToDate(undefined, from('en_US', 'Hello'))).toBeUndefined();
    });
});

describe('the counts of a locale', () => {
    it("should count the untranslated and outdated values of the entry's own fields and of its blocks at any depth", () => {
        const edited = danish(
            [
                translatedHeading('a', 'Hello there', 'Hello'),
                blockOf('accordion', 'b', [
                    blockOf('accordion_item', 'c', [], { title: { en_US: 'Shipping' }, content: { en_US: document('Free') } }),
                    blockOf('accordion_item', 'd', [], { title: { en_US: 'Returns', da_DK: 'Returnering' } }),
                ]),
            ],
            'About',
        );

        expect(localeCounts(edited, 'da_DK', schemaOf, standard)).toEqual({ untranslated: 1, outdated: 2 });
        expect(localeCounts(edited, 'en_US', schemaOf, standard)).toEqual({ untranslated: 0, outdated: 0 });
    });

    it('should leave out shared fields and fields of blocks of types the code no longer has', () => {
        const edited = danish([blockOf('removed', 'a', [], { text: { en_US: 'Hello' } }), block('b', { level: 'h3' })]);

        expect(localeCounts(edited, 'da_DK', schemaOf, standard)).toEqual({ untranslated: 0, outdated: 0 });
    });

    it('should leave out a block a locale condition hides in the locale, with everything inside it', () => {
        const notDanish = { kind: 'locale', negate: true, params: { locales: ['da_DK'] } };
        const onlyWeb = { kind: 'channel', negate: false, params: { channels: ['WEB'] } };
        const edited = danish([
            { ...blockOf('group', 'a', [block('b', { text: { en_US: 'Inside' } })]), conditions: [notDanish] },
            { ...block('c', { text: { en_US: 'Not in the web store' } }), conditions: [{ ...onlyWeb, negate: true }] },
        ]);

        expect(localeCounts(edited, 'da_DK', schemaOf, standard)).toEqual({ untranslated: 1, outdated: 0 });
        // The title, the slug and both headings, in a locale the entry has no translation in
        expect(localeCounts(edited, 'de_DE', schemaOf, standard)).toEqual({ untranslated: 4, outdated: 0 });
    });

    it('should count the label and URL a link keeps per locale as one value, and leave out a link for every locale', () => {
        const edited = danish([
            blockOf('button', 'a', [], { link: { target: 'url', ref: { en_US: 'https://example.com/en' }, anchor: null, label: { en_US: 'Shop now' } } }),
            { ...blockOf('button', 'b', [], { link: { target: 'product', ref: 'MUG', anchor: null, label: { en_US: 'Mugs', da_DK: 'Krus' } } }), translated: { link: { da_DK: from('en_US', { label: 'Mug' }) } } },
            blockOf('button', 'c', [], { shared: { target: 'product', ref: 'MUG', anchor: null, label: 'Mugs' } }),
        ]);

        expect(localeCounts(edited, 'da_DK', schemaOf, standard)).toEqual({ untranslated: 1, outdated: 1 });
        expect(localeCounts(edited, 'en_US', schemaOf, standard)).toEqual({ untranslated: 0, outdated: 0 });
    });

    it('should count an entry field that one translation is missing', () => {
        const edited = danish([]);
        edited.translations.da_DK = { shown: true, fields: { title: 'Om os', slug: null }, translated: {} };

        expect(localeCounts(edited, 'da_DK', schemaOf, standard)).toEqual({ untranslated: 1, outdated: 0 });
        expect(entryValues(edited, 'slug')).toEqual({ en_US: 'about-us', da_DK: null });
        expect(entryTranslated(danish([]), 'title')).toEqual({ da_DK: from('en_US', 'About us') });
    });
});

describe("recording where the edited locale's values are translated from", () => {
    it('should record the fallback value a block value is copied from or typed over, and save it with the value', () => {
        const store = storeOn(danish([block('a', { text: { en_US: 'Hello' } })]));

        store.getState().setFieldValue('a', 'text', 'Hej');

        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({ text: { da_DK: from('en_US', 'Hello') } });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: 'Hej' },
            { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: from('en_US', 'Hello') },
        ]);
    });

    it('should undo the value and where it was translated from in one step', () => {
        const store = storeOn(danish([block('a', { text: { en_US: 'Hello' } })]));
        store.getState().setFieldValue('a', 'text', 'H');
        store.getState().setFieldValue('a', 'text', 'Hej');

        store.getState().undo();

        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({});
        expect(store.getState().entry.tree.blocks[0]?.fields.text).toEqual({ en_US: 'Hello' });
    });

    it('should record where a value typed without a fallback value is translated from, as the server finds it, and nothing in a shared field', () => {
        const store = storeOn(danish([block('a', { text: { de_DE: 'Hallo' } })]));

        store.getState().setFieldValue('a', 'text', 'Hej');
        store.getState().setFieldValue('a', 'level', 'h3');

        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({ text: { da_DK: from('de_DE', 'Hallo') } });
        expect(store.getState().unsaved.map((change) => change.op)).toEqual(['set_value', 'set_translated', 'set_value']);
    });

    it('should forget where an emptied value was translated from', () => {
        const store = storeOn(danish([translatedHeading('a', 'Hello', 'Hello')]));

        store.getState().setFieldValue('a', 'text', '');

        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({});
        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: null },
            { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: null },
        ]);
    });

    it('should bring an outdated value up to date when it is updated', () => {
        const store = storeOn(danish([translatedHeading('a', 'Hello there', 'Hello')]));

        store.getState().setFieldValue('a', 'text', 'Hej der');

        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({ text: { da_DK: from('en_US', 'Hello there') } });
    });

    it('should mark an outdated block value up to date, and do nothing when it has no source', () => {
        const store = storeOn(danish([translatedHeading('a', 'Hello there', 'Hello'), block('b', { text: { en_US: 'Hi', da_DK: 'Hej' } })]));

        store.getState().markValueUpToDate('a', 'text');
        store.getState().markValueUpToDate('b', 'text');
        store.getState().markValueUpToDate('nope', 'text');

        expect(store.getState().entry.tree.blocks[0]?.fields.text).toEqual({ en_US: 'Hello there', da_DK: 'Hej' });
        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({ text: { da_DK: from('en_US', 'Hello there') } });
        expect(store.getState().entry.tree.blocks[1]?.translated).toEqual({});
        expect(store.getState().unsaved).toEqual([{ op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: from('en_US', 'Hello there') }]);
    });

    it('should not mark a value up to date with a source that has no value', () => {
        const store = storeOn(danish([{ ...block('a', { text: { da_DK: 'Hej' } }), translated: { text: { da_DK: from('en_US', 'Hello') } } }], null));

        store.getState().markValueUpToDate('a', 'text');

        expect(store.getState().unsaved).toEqual([]);
    });

    it('should record the title a translation is typed over, and mark an outdated title up to date', () => {
        const store = storeOn(danish([], 'About'));

        store.getState().markEntryValueUpToDate('title');

        expect(store.getState().entry.translations.da_DK?.translated).toEqual({ title: from('en_US', 'About us') });
        expect(store.getState().unsaved).toEqual([{ op: 'set_entry_translated', field: 'title', locale: 'da_DK', translated: from('en_US', 'About us') }]);

        store.getState().setLocale('de_CH');
        store.getState().setEntryValue('title', 'Über uns');

        expect(store.getState().entry.translations.de_CH).toEqual({ shown: true, fields: { title: 'Über uns' }, translated: { title: from('en_US', 'About us') } });
    });

    it('should not mark a title up to date that has no source', () => {
        const store = storeOn(danish([], null));

        store.getState().markEntryValueUpToDate('title');
        store.getState().markEntryValueUpToDate('slug');

        expect(store.getState().unsaved).toEqual([]);
    });

    it('should record no source for a slug, which never falls back', () => {
        const store = storeOn(danish([], null));
        store.getState().setLocale('de_CH');

        store.getState().setEntryValue('slug', 'ueber-uns');

        expect(store.getState().entry.translations.de_CH).toEqual({ shown: true, fields: { slug: 'ueber-uns' }, translated: {} });
    });

    it('should add a translation with where its values are translated from', () => {
        const store = storeOn(danish([], null), 'de_CH', 'published');

        store.getState().addTranslation({ title: 'About us', slug: 'about-us' }, { title: from('en_US', 'About us') });

        expect(store.getState().entry.translations.de_CH).toEqual({ shown: false, fields: { title: 'About us', slug: 'about-us' }, translated: { title: from('en_US', 'About us') } });

        store.getState().setLocale('da_DK');
        store.getState().addTranslation({ title: 'Om os' }, { title: from('en_US', 'About us') });

        expect(store.getState().entry.translations.da_DK?.translated).toEqual({ title: from('en_US', 'About us') });
    });
});

describe('the changes to where values were translated from', () => {
    it('should apply them by block, field and locale, and to a translation, adding it held back when the entry has none', () => {
        const changes: Change[] = [
            { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: from('en_US', 'Hello') },
            { op: 'set_translated', key: 'a', field: 'text', locale: 'de_CH', translated: from('en_US', 'Hello') },
            { op: 'set_translated', key: 'a', field: 'text', locale: 'de_CH', translated: null },
            { op: 'set_translated', key: 'gone', field: 'text', locale: 'da_DK', translated: null },
            { op: 'set_entry_translated', field: 'title', locale: 'da_DK', translated: null },
            { op: 'set_entry_translated', field: 'title', locale: 'de_CH', translated: from('en_US', 'About us') },
        ];

        const { entry: applied, applied: done, missing } = applyChanges(danish([block('a', { text: { en_US: 'Hello' } })]), changes);

        expect(applied.tree.blocks[0]?.translated).toEqual({ text: { da_DK: from('en_US', 'Hello') } });
        expect(applied.translations.da_DK?.translated).toEqual({});
        expect(applied.translations.de_CH).toEqual({ shown: false, fields: {}, translated: { title: from('en_US', 'About us') } });
        expect(done).toHaveLength(5);
        expect(missing).toEqual(['gone']);
    });

    it('should keep the last per block, field and locale, and per entry field and locale, and drop those of a removed block', () => {
        const merged = mergeChanges(
            [
                { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: null },
                { op: 'set_translated', key: 'a', field: 'text', locale: 'de_CH', translated: null },
                { op: 'set_translated', key: 'b', field: 'text', locale: 'da_DK', translated: null },
                { op: 'set_entry_translated', field: 'title', locale: 'da_DK', translated: null },
            ],
            [
                { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: from('en_US', 'Hello') },
                { op: 'set_entry_translated', field: 'title', locale: 'da_DK', translated: from('en_US', 'About us') },
                { op: 'remove_block', key: 'b' },
            ],
        );

        expect(merged).toEqual([
            { op: 'set_translated', key: 'a', field: 'text', locale: 'de_CH', translated: null },
            { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: from('en_US', 'Hello') },
            { op: 'set_entry_translated', field: 'title', locale: 'da_DK', translated: from('en_US', 'About us') },
            { op: 'remove_block', key: 'b' },
        ]);
    });

    it('should pull in where a colleague recorded values translated from, without anything to save', () => {
        const store = storeOn(danish([block('a', { text: { en_US: 'Hello', da_DK: 'Hej' } })], null));
        const merged = danish([translatedHeading('a', 'Hello', 'Hello')]);

        store.getState().pullIn(merged);

        expect(store.getState().entry.tree.blocks[0]?.translated).toEqual({ text: { da_DK: from('en_US', 'Hello') } });
        expect(store.getState().entry.translations.da_DK?.translated).toEqual({ title: from('en_US', 'About us') });
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should save where the values of a block are translated from with the block when undoing its removal puts it back', () => {
        const store = storeOn(danish([translatedHeading('a', 'Hello', 'Hello'), block('b')]));
        store.getState().removeBlock('a');
        store.getState().undo();

        const added = store.getState().unsaved.find((change) => change.op === 'add_block');
        expect(added?.op === 'add_block' ? added.block.translated : undefined).toEqual({ text: { da_DK: from('en_US', 'Hello') } });
    });

    it('should save where the values of a moved block are translated from when an edit changes them too', () => {
        const store = storeOn(danish([block('b'), translatedHeading('a', 'Hello there', 'Hello')]));

        store.getState().moveBlock('a', null, 0);
        store.getState().markValueUpToDate('a', 'text');
        store.getState().undo();
        store.getState().undo();
        store.getState().redo();
        store.getState().redo();

        expect(store.getState().unsaved.filter((change) => change.op === 'set_translated')).toEqual([
            { op: 'set_translated', key: 'a', field: 'text', locale: 'da_DK', translated: from('en_US', 'Hello there') },
        ]);
        expect(store.getState().entry.tree.blocks.map(({ key }) => key)).toEqual(['a', 'b']);
    });
});
