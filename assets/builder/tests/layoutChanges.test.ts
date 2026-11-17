import { enablePatches, produceWithPatches } from 'immer';
import { describe, expect, it } from 'vitest';
import { applyChanges, changesFromPatches, mergeChanges, type Change } from '../src/changeSet';
import type { Locale } from '../src/config';
import { fingerprint } from '../src/fingerprint';
import { createBuilderStore } from '../src/store';
import { localeCounts } from '../src/translationState';
import type { Entry, StoredLayout } from '../src/types';
import { blockTypes, entry, landing, page, standard } from './fixtures';

enablePatches();

const locales: Locale[] = [
    { code: 'en_US', name: 'English (United States)', fallbackChain: ['en_US'] },
    { code: 'da_DK', name: 'Danish (Denmark)', fallbackChain: ['da_DK', 'en_US'] },
];

function inLayout(layout: StoredLayout | null): Entry {
    const base = entry();

    return { ...base, tree: { ...base.tree, layout } };
}

function storeOn(edited: Entry, locale = 'en_US') {
    return createBuilderStore({ locale, locales, blockTypes, contentTypes: [page], entry: edited, status: 'never_published' });
}

const isTranslatable = (): boolean => false;
const noBlockFields = (): undefined => undefined;
const isLayoutTranslatable = (layout: string, field: string): boolean => layout === 'landing' && field === 'tagline';

describe('switching layout in the builder', () => {
    it('should switch to a layout with the values that carry over, as one change', () => {
        const store = storeOn(inLayout({ code: 'standard', version: 1, fields: { showTitle: false }, translated: {} }));

        store.getState().setLayout('landing');

        expect(store.getState().entry.tree.layout).toEqual({ code: 'landing', version: 2, fields: {}, translated: {} });
        expect(store.getState().unsaved).toEqual([{ op: 'set_layout', layout: { code: 'landing', version: 2, fields: {}, translated: {} } }]);
    });

    it('should leave the blocks and the entry fields as they are', () => {
        const store = storeOn(inLayout({ code: 'standard', version: 1, fields: {}, translated: {} }));
        const before = store.getState().entry;

        store.getState().setLayout('landing');

        expect(store.getState().entry.tree.blocks).toBe(before.tree.blocks);
        expect(store.getState().entry.translations).toBe(before.translations);
    });

    it('should bring the values a switch dropped back on undo, and send the layout as it was', () => {
        const store = storeOn(inLayout({ code: 'standard', version: 1, fields: {}, translated: {} }));
        store.getState().setLayoutValue('showTitle', false);
        store.getState().setLayout('landing');

        store.getState().undo();

        expect(store.getState().entry.tree.layout).toEqual({ code: 'standard', version: 1, fields: { showTitle: false }, translated: {} });
        expect(store.getState().unsaved).toEqual([{ op: 'set_layout', layout: { code: 'standard', version: 1, fields: { showTitle: false }, translated: {} } }]);
    });

    it('should do nothing when the layout picked is the one the entry is in, or one it does not know', () => {
        const store = storeOn(inLayout({ code: 'standard', version: 1, fields: { showTitle: false }, translated: {} }));

        store.getState().setLayout('standard');
        store.getState().setLayout('gone');

        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().changeCount).toBe(0);
    });

    it('should switch an entry in a layout the code no longer has to the one picked, which clears the flag', () => {
        const store = storeOn(inLayout({ code: 'gone', version: 1, fields: { tagline: { en_US: 'Big news' } }, translated: {} }));

        store.getState().setLayout('standard');

        expect(store.getState().entry.tree.layout).toEqual({ code: 'standard', version: 1, fields: {}, translated: {} });
    });

    it('should offer the content type layouts', () => {
        expect(storeOn(entry()).getState().layouts).toEqual([standard, landing]);
    });
});

describe('editing the layout fields', () => {
    it('should set a shared value with no locale and a translatable value in the edited locale, naming the layout', () => {
        const store = storeOn(inLayout({ code: 'landing', version: 2, fields: {}, translated: {} }));

        store.getState().setLayoutValue('transparentHeader', true);
        for (const text of ['B', 'Bi', 'Big']) {
            store.getState().setLayoutValue('tagline', text);
        }

        expect(store.getState().entry.tree.layout?.fields).toEqual({ transparentHeader: true, tagline: { en_US: 'Big' } });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_layout_value', layout: 'landing', field: 'transparentHeader', locale: null, value: true },
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: 'Big' },
        ]);

        store.getState().undo();
        expect(store.getState().entry.tree.layout?.fields).toEqual({ transparentHeader: true });
    });

    it('should record where a value typed over its fallback value is translated from, and mark an outdated one up to date', () => {
        const store = storeOn(inLayout({ code: 'landing', version: 2, fields: { tagline: { en_US: 'Big news' } }, translated: {} }), 'da_DK');

        store.getState().setLayoutValue('tagline', 'Nyhed');

        const source = { from: 'en_US', fingerprint: fingerprint('Big news') };
        expect(store.getState().entry.tree.layout?.translated).toEqual({ tagline: { da_DK: source } });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'da_DK', value: 'Nyhed' },
            { op: 'set_layout_translated', layout: 'landing', field: 'tagline', locale: 'da_DK', translated: source },
        ]);

        store.getState().setLocale('en_US');
        store.getState().setLayoutValue('tagline', 'Big news today');
        store.getState().setLocale('da_DK');
        store.getState().markLayoutValueUpToDate('tagline');

        expect(store.getState().entry.tree.layout?.translated).toEqual({ tagline: { da_DK: { from: 'en_US', fingerprint: fingerprint('Big news today') } } });
    });

    it('should not edit the fields of a layout the code no longer has, or a field the layout does not have', () => {
        const store = storeOn(inLayout({ code: 'gone', version: 1, fields: {}, translated: {} }));

        store.getState().setLayoutValue('tagline', 'Big news');
        store.getState().markLayoutValueUpToDate('tagline');
        storeOn(inLayout({ code: 'standard', version: 1, fields: {}, translated: {} })).getState().setLayoutValue('tagline', 'Big news');

        expect(store.getState().unsaved).toEqual([]);
    });

    it('should count the untranslated and outdated values of the layout the entry renders in', () => {
        const edited = inLayout({ code: 'landing', version: 2, fields: { tagline: { en_US: 'Big news' }, transparentHeader: true }, translated: {} });

        expect(localeCounts(edited, 'da_DK', noBlockFields, landing)).toEqual({ untranslated: 3, outdated: 0 });
        expect(localeCounts(edited, 'da_DK', noBlockFields, undefined)).toEqual({ untranslated: 2, outdated: 0 });
    });
});

describe('the layout changes of an edit', () => {
    it('should send a whole tree replaced in the same layout as the values that changed', () => {
        const before = inLayout({ code: 'landing', version: 2, fields: { tagline: { en_US: 'Big news' } }, translated: {} });
        const [after, patches] = produceWithPatches<Entry, Entry>(before, (working) => {
            working.tree = { layout: { code: 'landing', version: 2, fields: { tagline: { en_US: 'Big news', da_DK: 'Nyhed' }, transparentHeader: true }, translated: { tagline: { da_DK: { from: 'en_US', fingerprint: 'f' } } } }, blocks: [] };
        });

        expect(changesFromPatches(patches, before, after, isTranslatable, isLayoutTranslatable)).toEqual([
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'da_DK', value: 'Nyhed' },
            { op: 'set_layout_value', layout: 'landing', field: 'transparentHeader', locale: null, value: true },
            { op: 'set_layout_translated', layout: 'landing', field: 'tagline', locale: 'da_DK', translated: { from: 'en_US', fingerprint: 'f' } },
        ]);
    });

    it('should send a value taken away as null', () => {
        const before = inLayout({ code: 'landing', version: 2, fields: { tagline: { en_US: 'Big news' }, transparentHeader: true }, translated: {} });
        const [after, patches] = produceWithPatches<Entry, Entry>(before, (working) => {
            working.tree.layout = { code: 'landing', version: 2, fields: {}, translated: {} };
        });

        expect(changesFromPatches(patches, before, after, isTranslatable, isLayoutTranslatable)).toEqual([
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: null },
            { op: 'set_layout_value', layout: 'landing', field: 'transparentHeader', locale: null, value: null },
        ]);
    });

    it('should send a new version of the layout as a switch', () => {
        const before = inLayout({ code: 'landing', version: 1, fields: {}, translated: {} });
        const [after, patches] = produceWithPatches<Entry, Entry>(before, (working) => {
            working.tree.layout = { code: 'landing', version: 2, fields: {}, translated: {} };
        });

        expect(changesFromPatches(patches, before, after, isTranslatable, isLayoutTranslatable)).toEqual([{ op: 'set_layout', layout: { code: 'landing', version: 2, fields: {}, translated: {} } }]);
    });

    it('should send nothing for a tree whose layout is taken away', () => {
        const before = inLayout({ code: 'landing', version: 1, fields: {}, translated: {} });
        const [after, patches] = produceWithPatches<Entry, Entry>(before, (working) => {
            working.tree.layout = null;
        });

        expect(changesFromPatches(patches, before, after, isTranslatable, isLayoutTranslatable)).toEqual([]);
    });
});

describe('merging and applying layout changes', () => {
    const switchTo = (code: string): Change => ({ op: 'set_layout', layout: { code, version: 1, fields: {}, translated: {} } });

    it('should keep only the last switch, which carries the values set before it', () => {
        expect(
            mergeChanges(
                [
                    { op: 'set_layout_value', layout: 'standard', field: 'showTitle', locale: null, value: false },
                    { op: 'set_layout_translated', layout: 'standard', field: 'title', locale: 'da_DK', translated: null },
                    switchTo('landing'),
                ],
                [switchTo('standard'), { op: 'set_layout_value', layout: 'standard', field: 'showTitle', locale: null, value: true }],
            ),
        ).toEqual([switchTo('standard'), { op: 'set_layout_value', layout: 'standard', field: 'showTitle', locale: null, value: true }]);
    });

    it('should keep the last value and translation state per layout, field and locale', () => {
        expect(
            mergeChanges(
                [
                    { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: 'Big' },
                    { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'da_DK', value: 'Stor' },
                    { op: 'set_layout_translated', layout: 'landing', field: 'tagline', locale: 'da_DK', translated: null },
                ],
                [
                    { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: 'Big news' },
                    { op: 'set_layout_translated', layout: 'landing', field: 'tagline', locale: 'da_DK', translated: { from: 'en_US', fingerprint: 'f' } },
                ],
            ),
        ).toEqual([
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'da_DK', value: 'Stor' },
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: 'Big news' },
            { op: 'set_layout_translated', layout: 'landing', field: 'tagline', locale: 'da_DK', translated: { from: 'en_US', fingerprint: 'f' } },
        ]);
    });

    it('should apply a switch, and leave out a value set in a layout a colleague switched away from, as the server does', () => {
        const changes: Change[] = [
            { op: 'set_layout_value', layout: 'standard', field: 'showTitle', locale: null, value: false },
            { op: 'set_layout_translated', layout: 'standard', field: 'title', locale: 'da_DK', translated: null },
            switchTo('landing'),
            { op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: 'Big news' },
            { op: 'set_layout_translated', layout: 'landing', field: 'tagline', locale: 'da_DK', translated: { from: 'en_US', fingerprint: 'f' } },
        ];

        const { entry: applied, applied: kept, missing } = applyChanges(inLayout({ code: 'wide', version: 1, fields: {}, translated: {} }), changes);

        expect(applied.tree.layout).toEqual({ code: 'landing', version: 1, fields: { tagline: { en_US: 'Big news' } }, translated: { tagline: { da_DK: { from: 'en_US', fingerprint: 'f' } } } });
        expect(kept).toEqual(changes.slice(2));
        expect(missing).toEqual([]);
    });

    it('should leave out a layout value of a tree without a layout', () => {
        const { applied } = applyChanges(inLayout(null), [{ op: 'set_layout_value', layout: 'standard', field: 'showTitle', locale: null, value: false }]);

        expect(applied).toEqual([]);
    });
});
