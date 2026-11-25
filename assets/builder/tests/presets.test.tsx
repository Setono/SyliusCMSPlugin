import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { ApiError } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import { createNestingRules, canInsert } from '../src/nesting';
import { groupPresets, presetDocument, stylesheetsOf } from '../src/presets';
import type { EntryResponse, EntryStatus, InsertedPreset, PresenceResponse, PresetIndex, PresetListing, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { blockOf, blockTypes, entry, page, registry, settings, storeWith, strings } from './fixtures';

function preset(id: string, overrides: Partial<PresetListing> = {}): PresetListing {
    return { id, fromCode: true, name: id, category: null, description: null, thumbnail: null, fits: true, reasons: [], issues: [], blocks: [blockOf('heading', `${id}-heading`)], ...overrides };
}

describe('the presets the Presets tab lists', () => {
    const hero = preset('hero', { name: 'Hero', category: 'Banners', description: 'A heading and an introduction' });
    const sale = preset('sale', { name: 'Winter sale', category: 'Banners' });
    const pair = preset('pair', { name: 'Column pair', category: 'Layout', fits: false, reasons: ["Column isn't allowed here"] });
    const loose = preset('loose', { name: 'Loose ends' });
    const note = preset('note', { name: 'Product note', category: 'Products', fits: false, reasons: ['Needs: Product page'] });

    it('should group the presets offered by category in the order the categories come, those without one last', () => {
        const { groups, leftOut } = groupPresets([loose, hero, pair, sale, note], '');

        expect(groups.map(({ category, presets }) => [category, presets.map(({ id }) => id)])).toEqual([
            ['Banners', ['hero', 'sale']],
            [null, ['loose']],
        ]);
        expect(leftOut.map(({ id }) => id)).toEqual(['pair', 'note']);
    });

    it('should search their names, categories and descriptions, ignoring case', () => {
        expect(groupPresets([loose, hero, pair, sale, note], 'introduction').groups.flatMap(({ presets }) => presets.map(({ id }) => id))).toEqual(['hero']);
        expect(groupPresets([loose, hero, pair, sale, note], 'BANNERS').groups.flatMap(({ presets }) => presets.map(({ id }) => id))).toEqual(['hero', 'sale']);
        expect(groupPresets([loose, hero, pair, sale, note], 'product').leftOut.map(({ id }) => id)).toEqual(['note']);
    });
});

describe("a preset's live render", () => {
    it("should be a document with the preview page's stylesheets around the blocks' HTML in the tree's wrapper", () => {
        const html = presetDocument({ base: 'https://shop.example/en_US/about', stylesheets: '<link rel="stylesheet" href="/build/shop.css">' }, '<h2 class="cms-heading">Winter sale</h2>');

        expect(html).toBe(
            '<!doctype html><html><head><meta charset="utf-8"><base href="https://shop.example/en_US/about"><link rel="stylesheet" href="/build/shop.css"></head><body><div class="cms-tree">' +
                '<h2 class="cms-heading">Winter sale</h2></div></body></html>',
        );
    });

    it("should take the preview page's stylesheets and its address", () => {
        const page = document.implementation.createHTMLDocument('Shop');
        page.head.innerHTML = '<meta name="robots" content="noindex"><link rel="stylesheet" href="/build/shop.css"><style>.cms-tree{gap:1rem}</style><script src="/build/shop.js"></script>';

        expect(stylesheetsOf(page)).toEqual({ base: page.baseURI, stylesheets: '<link rel="stylesheet" href="/build/shop.css"><style>.cms-tree{gap:1rem}</style>' });
    });
});

describe('inserting blocks at a spot', () => {
    const rules = createNestingRules(blockTypes, page.blocks, undefined);

    it('should fit where the spot allows each block type, with room for all of them', () => {
        const tree = [blockOf('columns', 'columns', [blockOf('column', 'one'), blockOf('column', 'two')])];

        expect(canInsert(rules, tree, [blockOf('heading', 'a'), blockOf('group', 'b')], null)).toBe(true);
        expect(canInsert(rules, tree, [blockOf('column', 'a')], null)).toBe(false);
        expect(canInsert(rules, tree, [blockOf('column', 'a'), blockOf('column', 'b')], 'columns')).toBe(true);
        expect(canInsert(rules, tree, [blockOf('column', 'a'), blockOf('column', 'b'), blockOf('column', 'c')], 'columns')).toBe(false);
        expect(canInsert(rules, tree, [], null)).toBe(false);
        expect(canInsert(rules, tree, [blockOf('heading', 'a')], 'missing')).toBe(false);
    });

    it('should insert the blocks, with everything in them, at the index, and select the first', () => {
        const store = storeWith([blockOf('heading', 'first'), blockOf('heading', 'last')]);
        const group = blockOf('group', 'group', [blockOf('heading', 'inner', [], { text: { en_US: 'Winter sale' } })]);

        expect(store.getState().insertBlocks([group, blockOf('heading', 'after')], null, 1)).toEqual(['group', 'after']);

        expect(store.getState().entry.tree.blocks.map(({ key }) => key)).toEqual(['first', 'group', 'after', 'last']);
        expect(store.getState().entry.tree.blocks[1]?.children[0]?.fields).toEqual({ text: { en_US: 'Winter sale' } });
        expect(store.getState().selectedKey).toBe('group');
        expect(store.getState().unsaved.length).toBeGreaterThan(0);
    });

    it("should insert nothing where they don't fit", () => {
        const store = storeWith([]);

        expect(store.getState().insertBlocks([blockOf('column', 'column')], null)).toBeNull();
        expect(store.getState().entry.tree.blocks).toEqual([]);
    });
});

const presetStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.blocks_tab': 'Blocks',
    'setono_sylius_cms.builder.presets_tab': 'Presets',
    'setono_sylius_cms.builder.search_presets': 'Search presets',
    'setono_sylius_cms.builder.loading_presets': 'Loading presets…',
    'setono_sylius_cms.builder.presets_failed': "The presets couldn't be loaded.",
    'setono_sylius_cms.builder.no_matching_presets': 'No presets match.',
    'setono_sylius_cms.builder.preset_uncategorised': 'Other',
    'setono_sylius_cms.builder.preset_from_code': 'From code',
    'setono_sylius_cms.builder.preset_invalid': 'Invalid',
    'setono_sylius_cms.builder.preset_preview': 'Preview of %name%',
    'setono_sylius_cms.builder.insert_preset': 'Insert %name%',
    'setono_sylius_cms.builder.not_available_here': 'Not available here',
};

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    overviewUrl: '/admin/cms/entry/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    endpoints: {
        registry: '/registry',
        entry: '/entry',
        validate: '/validate',
        publish: '/publish',
        discard: '/discard',
        schedule: '/schedule',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
    timeZone: 'UTC',
};

function fakeApi(presets: PresetListing[], insertPreset: (id: string) => Promise<InsertedPreset>, blocks: StoredBlock[] = []) {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, strings: presetStrings })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(() => Promise.reject(new Error('Not discarded in this test'))),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (_token: string, rendered: readonly StoredBlock[]): Promise<Record<string, string>> => Object.fromEntries(rendered.map((block) => [block.key, `<h2>${block.key}</h2>`]))),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        presets: vi.fn(async (_parent: string | null): Promise<PresetIndex> => ({ presets, startingPoints: [], categories: [] })),
        insertPreset: vi.fn(insertPreset),
    } satisfies BuilderApi;
}

async function openPresetsTab(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
    fireEvent.click(screen.getAllByRole('button', { name: 'Add block' })[0] as HTMLElement);
    const picker = within(await screen.findByRole('dialog', { name: 'Add block' }));
    fireEvent.click(picker.getByRole('tab', { name: 'Presets' }));

    return picker;
}

describe('the Presets tab', () => {
    const hero = preset('hero', { name: 'Hero', category: 'Banners', description: 'A heading and an introduction', blocks: [blockOf('heading', 'hero-heading', [], { text: { en_US: 'Winter sale' } })] });
    const promo = preset('promo', { name: 'Promo', fromCode: false, category: 'Banners', issues: ['It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.'] });
    const note = preset('note', { name: 'Product note', category: 'Products', fits: false, reasons: ['Needs: Product page'] });

    it('should list the presets that fit by category, mark those from code and those with invalid blocks, and name those left out with why', async () => {
        const api = fakeApi([hero, promo, note], async () => ({ blocks: [], notices: [] }));
        const picker = await openPresetsTab(api);

        const banners = within(await picker.findByRole('group', { name: 'Banners' }));
        expect(banners.getAllByRole('heading', { level: 4 }).map((name) => name.textContent)).toEqual(['HeroFrom code', 'Promo']);
        expect(banners.getByText('A heading and an introduction')).toBeInTheDocument();
        expect(banners.getByText('It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.')).toBeInTheDocument();
        expect(picker.queryByRole('group', { name: 'Products' })).toBeNull();
        const leftOut = within(picker.getByRole('list', { name: 'Not available here' }));
        expect(leftOut.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Product noteNeeds: Product page']);
        expect(api.presets).toHaveBeenCalledWith(null);
    });

    it('should mark a preset left out that has invalid blocks too, with the reason', async () => {
        const broken = preset('broken', { name: 'Broken note', category: 'Products', fits: false, reasons: ['Needs: Product page'], issues: ['It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.'] });
        const picker = await openPresetsTab(fakeApi([hero, broken], async () => ({ blocks: [], notices: [] })));

        const leftOut = within(await picker.findByRole('list', { name: 'Not available here' }));
        const issues = within(leftOut.getByRole('list', { name: 'Invalid' }));
        expect(issues.getByText('It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.')).toBeInTheDocument();
    });

    it('should search the presets', async () => {
        const picker = await openPresetsTab(fakeApi([hero, promo, note], async () => ({ blocks: [], notices: [] })));
        await picker.findByRole('group', { name: 'Banners' });

        fireEvent.change(picker.getByRole('searchbox', { name: 'Search presets' }), { target: { value: 'introduction' } });

        expect(picker.getAllByRole('heading', { level: 4 }).map((name) => name.textContent)).toEqual(['HeroFrom code']);
        expect(picker.queryByRole('list', { name: 'Not available here' })).toBeNull();
    });

    it("should render each preset live, under the preview's token", async () => {
        const api = fakeApi([hero, promo], async () => ({ blocks: [], notices: [] }));
        const picker = await openPresetsTab(api);

        await waitFor(() => expect(api.renderPreview).toHaveBeenCalledWith('abc.def', [...hero.blocks, ...promo.blocks]));
        const frame = await picker.findByTitle('Preview of Hero');
        expect(frame.getAttribute('srcdoc')).toContain('<div class="cms-tree"><h2>hero-heading</h2></div>');
    });

    it('should insert the blocks of the preset picked, which the tree then shows, with what the editor is told', async () => {
        const inserted = blockOf('heading', 'inserted', [], { text: { en_US: 'Winter sale' } });
        const picker = await openPresetsTab(fakeApi([hero], async () => ({ blocks: [inserted], notices: ['Blocks of the type "gallery", which the code no longer has, were left out.'] })));

        fireEvent.click(await picker.findByRole('button', { name: 'Insert Hero' }));

        await waitFor(() => expect(within(screen.getByRole('navigation', { name: 'Blocks' })).getByRole('button', { name: 'Heading Winter sale' })).toBeInTheDocument());
        expect(await picker.findByRole('status')).toHaveTextContent('Blocks of the type "gallery", which the code no longer has, were left out.');
    });

    it('should say why the server refused to insert a preset', async () => {
        const picker = await openPresetsTab(fakeApi([hero], async () => Promise.reject(new ApiError('"heading" in the preset was saved with version 9.', 422))));

        const insert = await picker.findByRole('button', { name: 'Insert Hero' });
        await act(async () => fireEvent.click(insert));

        expect(await picker.findByRole('alert')).toHaveTextContent('"heading" in the preset was saved with version 9.');
        expect(within(screen.getByRole('navigation', { name: 'Blocks' })).queryByRole('button', { name: 'Heading Winter sale' })).toBeNull();
    });
});

describe('creating from a starting point', () => {
    it('should tell the editor once the builder opens what creating left out, until the editor dismisses it', async () => {
        render(<App config={{ ...config, notices: ["Spacer blocks are switched off, so the preset's were left out."] }} api={fakeApi([], async () => ({ blocks: [], notices: [] }))} autosaveDelay={10} />);
        await screen.findByRole('heading', { level: 1 });

        const notice = screen.getByText("Spacer blocks are switched off, so the preset's were left out.");
        expect(notice.closest('[role="status"]')).not.toBeNull();

        fireEvent.click(screen.getByRole('button', { name: 'setono_sylius_cms.builder.dismiss' }));
        expect(screen.queryByText("Spacer blocks are switched off, so the preset's were left out.")).toBeNull();
    });
});
