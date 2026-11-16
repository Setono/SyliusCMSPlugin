import { describe, expect, it } from 'vitest';
import { applyChanges, mergeChanges, type Change } from '../src/changeSet';
import { fallbackValue, isMissing } from '../src/fallback';
import { holdClashes, splitValueClashes } from '../src/pullIn';
import { createBuilderStore } from '../src/store';
import type { Entry, EntryStatus, JsonValue } from '../src/types';
import { block, blockTypes, entry, page, storeWith } from './fixtures';

/** A store on the entry, edited in a locale, never published unless the status says otherwise. */
function storeOn(edited: Entry, locale = 'en_US', status: EntryStatus = 'never_published') {
    return createBuilderStore({ locale, blockTypes, contentTypes: [page], entry: edited, status });
}

/** An entry with an English and a Danish translation. */
function translated(blocks = [block('a', { text: { en_US: 'Hello' } })]): Entry {
    const base = entry(blocks);

    return { ...base, translations: { ...base.translations, da_DK: { shown: true, fields: { slug: 'om-os', title: 'Om os' }, translated: {} } } };
}

const emptyDocument: JsonValue = { type: 'doc', content: [{ type: 'paragraph' }] };

const document = (text: string): JsonValue => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text }] }] });

describe('editing one locale at a time', () => {
    it('should edit a value in the edited locale and leave the other locales alone', () => {
        const store = storeOn(translated(), 'da_DK');

        store.getState().setFieldValue('a', 'text', 'Hej');

        expect(store.getState().entry.tree.blocks[0]?.fields.text).toEqual({ en_US: 'Hello', da_DK: 'Hej' });
        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: 'Hej' }]);
    });

    it('should switch the edited locale without an edit to save or undo', () => {
        const store = storeOn(translated());

        store.getState().setLocale('da_DK');

        expect(store.getState().locale).toBe('da_DK');
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().history.done).toHaveLength(0);
        expect(store.getState().changeCount).toBe(0);
    });

    it('should undo an edit in the locale it was made in, whichever locale is edited now', () => {
        const store = storeOn(translated(), 'da_DK');
        store.getState().setFieldValue('a', 'text', 'Hej');
        store.getState().setLocale('en_US');

        store.getState().undo();

        expect(store.getState().entry.tree.blocks[0]?.fields.text).toEqual({ en_US: 'Hello' });
    });

    it('should delete a block with its values in every locale', () => {
        const store = storeOn(translated([block('a', { text: { en_US: 'Hello', da_DK: 'Hej' } }), block('b')]));

        store.getState().removeBlock('a');

        expect(store.getState().entry.tree.blocks.map(({ key }) => key)).toEqual(['b']);
        expect(store.getState().unsaved).toEqual([{ op: 'remove_block', key: 'a' }]);
    });
});

describe('a translation in the edited locale', () => {
    it('should set its title and slug, and take one away', () => {
        const store = storeOn(translated(), 'da_DK');

        store.getState().setEntryValue('title', 'Om dem');
        store.getState().setEntryValue('slug', '');

        expect(store.getState().entry.translations.da_DK).toEqual({ shown: true, fields: { slug: null, title: 'Om dem' }, translated: {} });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'Om dem' },
            { op: 'set_entry_value', field: 'slug', locale: 'da_DK', value: null },
        ]);
    });

    it('should be added shown when a value is set in a locale the entry has none in before the first publish', () => {
        const store = storeOn(entry(), 'da_DK');

        store.getState().setEntryValue('title', 'Om os');

        expect(store.getState().entry.translations.da_DK).toEqual({ shown: true, fields: { title: 'Om os' }, translated: {} });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_shown', locale: 'da_DK', shown: true },
            { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'Om os' },
        ]);
    });

    it('should be added held back after the first publish', () => {
        const store = storeOn(entry(), 'da_DK', 'published');

        store.getState().setEntryValue('title', 'Om os');

        expect(store.getState().entry.translations.da_DK?.shown).toBe(false);
    });

    it('should be added with the fields it is given, such as the title and slug it falls back to', () => {
        const store = storeOn(entry(), 'da_DK', 'unpublished_changes');

        store.getState().addTranslation({ slug: 'about-us', title: 'About us' });

        expect(store.getState().entry.translations.da_DK).toEqual({ shown: false, fields: { slug: 'about-us', title: 'About us' }, translated: {} });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_shown', locale: 'da_DK', shown: false },
            { op: 'set_entry_value', field: 'slug', locale: 'da_DK', value: 'about-us' },
            { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'About us' },
        ]);

        store.getState().undo();

        expect(store.getState().entry.translations.da_DK).toBeUndefined();
    });

    it('should switch it shown and held back', () => {
        const store = storeOn(translated(), 'da_DK');

        store.getState().setShown(false);

        expect(store.getState().entry.translations.da_DK?.shown).toBe(false);
        expect(store.getState().unsaved).toEqual([{ op: 'set_shown', locale: 'da_DK', shown: false }]);

        store.getState().undo();

        expect(store.getState().entry.translations.da_DK?.shown).toBe(true);
    });

    it('should not switch a translation the entry does not have', () => {
        const store = storeOn(entry(), 'da_DK');

        store.getState().setShown(true);

        expect(store.getState().entry.translations.da_DK).toBeUndefined();
        expect(store.getState().unsaved).toEqual([]);
    });
});

describe('the change set of translations', () => {
    it('should keep the last value per entry field and locale, and the last switch per locale', () => {
        const changes = mergeChanges(
            [
                { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'Om' },
                { op: 'set_shown', locale: 'da_DK', shown: false },
                { op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About' },
            ],
            [
                { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'Om os' },
                { op: 'set_shown', locale: 'da_DK', shown: true },
            ],
        );

        expect(changes).toEqual([
            { op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About' },
            { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'Om os' },
            { op: 'set_shown', locale: 'da_DK', shown: true },
        ]);
    });

    it('should apply translations and their switches the way the server does', () => {
        const changes: Change[] = [
            { op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About' },
            { op: 'set_entry_value', field: 'slug', locale: 'en_US', value: null },
            { op: 'set_shown', locale: 'de_DE', shown: true },
            { op: 'set_entry_value', field: 'title', locale: 'de_DE', value: 'Über uns' },
            { op: 'set_entry_value', field: 'title', locale: 'fr_FR', value: 'À propos' },
        ];

        const { entry: applied, applied: done, missing } = applyChanges(entry(), changes);

        expect(applied.translations).toEqual({
            en_US: { shown: true, fields: { slug: null, title: 'About' }, translated: {} },
            de_DE: { shown: true, fields: { title: 'Über uns' }, translated: {} },
            fr_FR: { shown: false, fields: { title: 'À propos' }, translated: {} },
        });
        expect(done).toEqual(changes);
        expect(missing).toEqual([]);
    });
});

describe('pulling in a colleague\'s translations', () => {
    it('should hold a title not yet saved as a clash when they changed it in the same locale since the save was sent', () => {
        const base = entry();
        const merged: Entry = { ...base, translations: { en_US: { shown: true, fields: { slug: 'about-us', title: 'About them' }, translated: {} } } };
        const unsaved: Change[] = [
            { op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About me' },
            { op: 'set_entry_value', field: 'slug', locale: 'en_US', value: 'about-me' },
        ];

        const { kept, clashes } = splitValueClashes(unsaved, base, merged);

        expect(kept).toEqual([unsaved[1]]);
        expect(clashes).toEqual([{ kind: 'entry_value', field: 'title', locale: 'en_US', mine: 'About me', theirs: 'About them' }]);
        expect(holdClashes(base, merged, clashes)).toEqual(clashes);
    });

    it('should let the editor keep their title over a colleague\'s', () => {
        const store = storeWith();
        const merged: Entry = { ...entry(), translations: { en_US: { shown: true, fields: { slug: 'about-us', title: 'About them' }, translated: {} } } };

        store.getState().pullIn(merged, [{ kind: 'entry_value', field: 'title', locale: 'en_US', mine: 'About me', theirs: 'About them' }]);
        expect(store.getState().entry.translations.en_US?.fields.title).toBe('About them');

        store.getState().resolveClash('mine');

        expect(store.getState().entry.translations.en_US?.fields.title).toBe('About me');
        expect(store.getState().unsaved).toEqual([{ op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About me' }]);
    });
});

describe('the fallback chain', () => {
    it.each<[string, JsonValue | undefined]>([
        ['nothing', undefined],
        ['null', null],
        ['an empty string', ''],
        ['an empty list', []],
        ['an empty rich text document', emptyDocument],
        ['a document with only whitespace and a line break', { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: ' ' }, { type: 'hardBreak' }] }] }],
    ])('should count %s as missing', (_, value) => {
        expect(isMissing(value)).toBe(true);
    });

    it.each<[string, JsonValue]>([
        ['text', 'Hello'],
        ['the string zero', '0'],
        ['false', false],
        ['zero', 0],
        ['a document with text', document('Hello')],
        ['a document with a horizontal line', { type: 'doc', content: [{ type: 'horizontalRule' }] }],
    ])('should count %s as there', (_, value) => {
        expect(isMissing(value)).toBe(false);
    });

    it('should find the value a field falls back to past the locales that are missing it', () => {
        const values: JsonValue = { de_CH: '', fr_FR: emptyDocument, en_US: 'Hello', de_DE: 'Hallo' };

        expect(fallbackValue(values, ['de_CH', 'fr_FR', 'de_DE', 'en_US'])).toEqual({ locale: 'de_DE', value: 'Hallo' });
        expect(fallbackValue(values, ['de_CH', 'fr_FR', 'en_US'])).toEqual({ locale: 'en_US', value: 'Hello' });
        expect(fallbackValue(values, ['de_CH', 'fr_FR'])).toBeUndefined();
        expect(fallbackValue('not a locale map', ['de_CH', 'en_US'])).toBeUndefined();
    });

    it('should not fall back to the locale the field is in', () => {
        expect(fallbackValue({ en_US: 'Hello' }, ['en_US'])).toBeUndefined();
    });
});
