import { describe, expect, it } from 'vitest';
import { applyChanges, type Change } from '../src/changeSet';
import { holdClashes, splitValueClashes } from '../src/pullIn';
import { createBuilderStore } from '../src/store';
import type { Entry } from '../src/types';
import { blockTypes, entry, page } from './fixtures';

/** A page with its SEO fields, as the server sends one: "Hide from search engines" is off. */
function seoEntry(): Entry {
    const base = entry();

    return { ...base, fields: { noindex: false } };
}

function storeOn(edited: Entry, locale = 'en_US') {
    return createBuilderStore({
        locale,
        locales: [
            { code: 'en_US', name: 'English', fallbackChain: ['en_US'] },
            { code: 'da_DK', name: 'Danish', fallbackChain: ['da_DK', 'en_US'] },
        ],
        blockTypes,
        contentTypes: [{ ...page, prefix: '' }],
        entry: edited,
        status: 'published',
    });
}

describe('the SEO fields', () => {
    it('should send Hide from search engines, which all translations share, with no locale, and undo it', () => {
        const store = storeOn(seoEntry());

        store.getState().setSharedEntryValue('noindex', true);

        expect(store.getState().entry.fields).toEqual({ noindex: true });
        expect(store.getState().entry.translations).toEqual(seoEntry().translations);
        expect(store.getState().unsaved).toEqual([{ op: 'set_entry_value', field: 'noindex', locale: null, value: true }]);

        store.getState().setSharedEntryValue('noindex', false);
        expect(store.getState().unsaved).toEqual([{ op: 'set_entry_value', field: 'noindex', locale: null, value: false }]);

        store.getState().undo();
        expect(store.getState().entry.fields).toEqual({ noindex: false });
    });

    it('should send a meta title typed in a locale with where it is translated from, like the title', () => {
        const base = seoEntry();
        const store = storeOn(
            { ...base, translations: { ...base.translations, da_DK: { shown: true, fields: { slug: 'om-os', title: 'Om os' }, translated: {} }, en_US: { shown: true, fields: { metaTitle: 'About us | Shop', slug: 'about-us', title: 'About us' }, translated: {} } } },
            'da_DK',
        );

        store.getState().setEntryValue('metaTitle', 'About us | Shop');

        expect(store.getState().unsaved).toEqual([
            { op: 'set_entry_value', field: 'metaTitle', locale: 'da_DK', value: 'About us | Shop' },
            { op: 'set_entry_translated', field: 'metaTitle', locale: 'da_DK', translated: { from: 'en_US', fingerprint: expect.any(String) as string } },
        ]);
    });

    it('should apply Hide from search engines the way the server does', () => {
        const changes: Change[] = [{ op: 'set_entry_value', field: 'noindex', locale: null, value: true }];

        const { entry: applied, applied: done } = applyChanges(seoEntry(), changes);

        expect(applied.fields).toEqual({ noindex: true });
        expect(applied.translations).toEqual(seoEntry().translations);
        expect(done).toEqual(changes);
    });

    it('should hold Hide from search engines not yet saved as a clash when a colleague changed it since the save was sent', () => {
        const base = seoEntry();
        const merged: Entry = { ...base, fields: { noindex: true } };
        const unsaved: Change[] = [{ op: 'set_entry_value', field: 'noindex', locale: null, value: false }];

        expect(splitValueClashes(unsaved, base, base)).toEqual({ kept: unsaved, clashes: [] });

        const { kept, clashes } = splitValueClashes([{ op: 'set_entry_value', field: 'noindex', locale: null, value: true }], { ...base, fields: { noindex: true } }, { ...base, fields: { noindex: false } });

        expect(kept).toEqual([]);
        expect(clashes).toEqual([{ kind: 'entry_value', field: 'noindex', locale: null, mine: true, theirs: false }]);
        expect(holdClashes(base, merged, clashes)).toEqual(clashes);
    });

    it('should let the editor keep their Hide from search engines over a colleague\'s', () => {
        const store = storeOn(seoEntry());

        store.getState().pullIn({ ...seoEntry(), fields: { noindex: true } }, [{ kind: 'entry_value', field: 'noindex', locale: null, mine: false, theirs: true }]);
        expect(store.getState().entry.fields).toEqual({ noindex: true });

        store.getState().resolveClash('mine');

        expect(store.getState().entry.fields).toEqual({ noindex: false });
        expect(store.getState().unsaved).toEqual([{ op: 'set_entry_value', field: 'noindex', locale: null, value: false }]);
    });
});
