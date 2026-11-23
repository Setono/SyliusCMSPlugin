import { act, fireEvent, render, renderHook, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { Change } from '../src/changeSet';
import type { BuilderConfig, SnippetPlacement } from '../src/config';
import { BuilderProvider, useProvidedContext, type BuilderSession } from '../src/context';
import { contextBlockers, contextChoices, contextNames, declaredContext, missingContext, providedContext } from '../src/hostPageContext';
import { createTranslator } from '../src/i18n';
import { createNestingRules, insertableIn, leftOutIn } from '../src/nesting';
import { createBuilderStore } from '../src/store';
import type {
    AllowedBlockType,
    BlockTypeDefinition,
    ConditionKindDefinition,
    ContentTypeDefinition,
    ContextKeyDefinition,
    Entry,
    EntryResponse,
    EntryStatus,
    PresenceResponse,
    Registry,
    SaveResponse,
    SlotDefinition,
    StoredBlock,
    ValidationError,
} from '../src/types';
import { blockOf, conditionKinds, entry, heading, registry, settings, strings } from './fixtures';

const productNote: BlockTypeDefinition = { code: 'product_note', label: 'Product note', version: 1, thumbnail: null, disabled: false, parents: null, children: [], styles: [], needs: ['product'], fields: [] };

const taxonBanner: BlockTypeDefinition = { ...productNote, code: 'taxon_banner', label: 'Taxon banner', needs: ['taxon'] };

const onProductPage: ConditionKindDefinition = { code: 'on_product_page', label: 'On a product page', matches: 'is one of', matchesNot: 'is not one of', needs: ['product'], fields: [] };

const allowed: AllowedBlockType[] = ['heading', 'product_note', 'taxon_banner'].map((type) => ({ type, min: null, max: null }));

const page: ContentTypeDefinition = { code: 'page', label: 'Page', blocks: allowed, layouts: [] };

const snippet: ContentTypeDefinition = { code: 'snippet', label: 'Snippet', blocks: allowed, provides: null, layouts: [] };

const contextKeys: ContextKeyDefinition[] = [
    { key: 'product', label: 'Product page' },
    { key: 'taxon', label: 'Taxon page' },
    { key: 'entry', label: 'Entry page' },
];

const slots: SlotDefinition[] = [
    { identifier: 'shop.footer', label: 'Every page: the footer', provides: [] },
    { identifier: 'product.after_price', label: 'Product page: under the price', provides: ['product'] },
    { identifier: 'taxon.header', label: 'Taxon page: the header', provides: ['taxon'] },
];

const contextStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.context': 'Shown on',
    'setono_sylius_cms.builder.context_any_page': 'Any page',
    'setono_sylius_cms.builder.context_help': 'The pages it can be placed on.',
    'setono_sylius_cms.builder.context_in_the_way': "This can't change while these need it:",
    'setono_sylius_cms.builder.context_block_needs': 'The block "%block%" needs the host-page context %context%.',
    'setono_sylius_cms.builder.context_condition_needs': 'The condition "%kind%" on the block "%block%" needs the host-page context %context%.',
    'setono_sylius_cms.builder.context_placement_does_not_provide': 'The placement in "%slot%" doesn\'t provide the host-page context %context%.',
    'setono_sylius_cms.builder.context_removed_key': '%key%, which the code no longer declares',
    'setono_sylius_cms.builder.needs_context': 'Needs: %context%',
    'setono_sylius_cms.builder.not_available_here': 'Not available here',
    'setono_sylius_cms.builder.visibility': 'Visibility',
    'setono_sylius_cms.builder.visibility_help': 'Shown to everyone',
    'setono_sylius_cms.builder.add_condition': 'Add condition',
    'setono_sylius_cms.builder.condition': 'Condition %number%',
    'setono_sylius_cms.builder.condition_kind': 'Kind',
};

const t = createTranslator(contextStrings);

const contextRegistry: Registry = {
    ...registry,
    blockTypes: [heading, productNote, taxonBanner],
    contentTypes: [page, snippet],
    conditionKinds: [onProductPage, ...conditionKinds],
    contextKeys,
    slots,
    strings: contextStrings,
};

function snippetEntry(context: string[] | null, blocks: StoredBlock[] = []): Entry {
    return { ...entry(blocks), contentType: 'snippet', fields: context === null ? {} : { context }, tree: { layout: null, blocks } };
}

function withCondition(stored: StoredBlock, kind: string): StoredBlock {
    return { ...stored, conditions: [{ kind, negate: false, params: {} }] };
}

function placement(slot: string, label: string, removed = false): SnippetPlacement {
    return { uuid: `0193a5be-71b0-7a3c-9d4e-${slot.length.toString().padStart(12, '0')}`, slot, label, removed };
}

describe('the host-page context an entry provides', () => {
    it('should be the entry itself for an entry of a content type that provides it', () => {
        expect(providedContext(undefined, entry())).toEqual(['entry']);
        expect(providedContext(['entry'], entry())).toEqual(['entry']);
    });

    it('should be what a snippet declares in its context field, or nothing', () => {
        expect(providedContext(null, snippetEntry(['product']))).toEqual(['product']);
        expect(providedContext(null, snippetEntry(null))).toEqual([]);
        expect(declaredContext(snippetEntry(['product', 'taxon']))).toEqual(['product', 'taxon']);
    });

    it('should find what something needs that is not provided', () => {
        expect(missingContext(['product'], ['entry'])).toEqual(['product']);
        expect(missingContext(['product'], ['taxon', 'product'])).toEqual([]);
        expect(missingContext(undefined, [])).toEqual([]);
        expect(missingContext(['product', 'brand'], [])).toEqual(['product', 'brand']);
    });

    it('should name context keys by their labels, and one the code no longer declares by itself', () => {
        expect(contextNames(['product', 'taxon'], contextKeys, t)).toEqual(['Product page', 'Taxon page']);
        expect(contextNames(['brand'], contextKeys, t)).toEqual(['brand, which the code no longer declares']);
    });
});

describe("a snippet's choices of context", () => {
    it('should offer any page, each context key, and the keys each slot provides together, once each', () => {
        const both = { identifier: 'app.product_in_taxon', label: 'A product in a taxon', provides: ['product', 'taxon'] };

        expect(contextChoices(contextKeys, [...slots, both], [])).toEqual([[], ['product'], ['taxon'], ['entry'], ['product', 'taxon']]);
    });

    it('should offer what the snippet declares, even when no slot provides it', () => {
        expect(contextChoices(contextKeys, slots, ['brand'])).toEqual([[], ['product'], ['taxon'], ['entry'], ['brand']]);
        expect(contextChoices(contextKeys, slots, ['taxon'])).toEqual([[], ['product'], ['taxon'], ['entry']]);
    });
});

describe("what's in the way of changing a snippet's context", () => {
    const blockTypes = new Map([heading, productNote, taxonBanner].map((blockType) => [blockType.code, blockType]));
    const kinds = [onProductPage, ...conditionKinds];

    it('should be the blocks and conditions that need a key it removes', () => {
        const tree = [blockOf('product_note', 'note'), blockOf('taxon_banner', 'banner'), withCondition(blockOf('heading', 'title'), 'on_product_page')];

        expect(contextBlockers(['product', 'taxon'], ['taxon'], { blocks: tree, blockTypes, conditionKinds: kinds, placements: [], slots })).toEqual([
            { kind: 'block', block: 'Product note', keys: ['product'] },
            { kind: 'condition', condition: 'On a product page', block: 'Heading', keys: ['product'] },
        ]);
    });

    it("should be the placements whose slot doesn't provide a key it adds, but not one in a slot the code no longer has", () => {
        const placements = [placement('product.after_price', 'Product page: under the price'), placement('shop.footer', 'Every page: the footer'), placement('shop.gone', 'shop.gone', true)];

        expect(contextBlockers([], ['product'], { blocks: [], blockTypes, conditionKinds: kinds, placements, slots })).toEqual([{ kind: 'placement', slot: 'Every page: the footer', keys: ['product'] }]);
        expect(contextBlockers(['product'], [], { blocks: [], blockTypes, conditionKinds: kinds, placements, slots })).toEqual([]);
    });
});

describe('the block types the picker offers', () => {
    const rules = createNestingRules([heading, productNote, taxonBanner], allowed, null);

    it('should leave out those whose needs the entry does not provide, with what is missing', () => {
        expect(insertableIn(rules, [], null, ['product']).map(({ code }) => code)).toEqual(['heading', 'product_note']);
        expect(leftOutIn(rules, [], null, ['product'])).toEqual([{ blockType: taxonBanner, missing: ['taxon'] }]);
        expect(insertableIn(rules, [], null, []).map(({ code }) => code)).toEqual(['heading']);
    });

    it("should not add a block whose type needs context the entry doesn't provide", () => {
        const store = createBuilderStore({ locale: 'en_US', blockTypes: [heading, productNote, taxonBanner], contentTypes: [page], entry: entry(), status: 'never_published' });

        expect(store.getState().addBlock('product_note')).toBeNull();
        expect(store.getState().addBlock('heading')).not.toBeNull();
    });

    it('should add one a snippet declares the context of', () => {
        const store = createBuilderStore({ locale: 'en_US', blockTypes: [heading, productNote, taxonBanner], contentTypes: [snippet], entry: snippetEntry(['product']), status: 'never_published' });

        expect(store.getState().addBlock('product_note')).not.toBeNull();
        expect(store.getState().addBlock('taxon_banner')).toBeNull();
    });
});

describe('the context the edited entry provides, as a hook', () => {
    it("should follow a snippet's declared context as the editor changes it", () => {
        const store = createBuilderStore({ locale: 'en_US', blockTypes: [heading], contentTypes: [snippet], entry: snippetEntry([]), status: 'never_published' });
        const { result } = renderHook(() => useProvidedContext(), {
            wrapper: ({ children }) => <BuilderProvider value={{ store } as unknown as BuilderSession}>{children}</BuilderProvider>,
        });
        expect(result.current).toEqual([]);

        act(() => store.getState().setSharedEntryValue('context', ['product']));
        const product = result.current;
        expect(product).toEqual(['product']);

        act(() => store.getState().setSharedEntryValue('noindex', true));
        expect(result.current).toBe(product);
    });

    it('should provide what the content type provides, and nothing outside the builder', () => {
        const store = createBuilderStore({ locale: 'en_US', blockTypes: [heading], contentTypes: [page], entry: entry(), status: 'never_published' });

        expect(renderHook(() => useProvidedContext(), { wrapper: ({ children }) => <BuilderProvider value={{ store } as unknown as BuilderSession}>{children}</BuilderProvider> }).result.current).toEqual(['entry']);
        expect(renderHook(() => useProvidedContext()).result.current).toBeNull();
    });
});

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

function fakeApi(edited: Entry, saveChanges = vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 }))) {
    return {
        fetchRegistry: vi.fn(async () => contextRegistry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: edited, settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges,
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(() => Promise.reject(new Error('Not discarded in this test'))),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/_cms/snippet-preview?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    } satisfies BuilderApi;
}

async function openBuilder(api: BuilderApi, placements: SnippetPlacement[] | null = null): Promise<void> {
    render(<App config={{ ...config, placements }} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function pane() {
    return within(screen.getByRole('complementary'));
}

describe('the block picker', () => {
    it('should name the block types it leaves out of a page with what they need', async () => {
        await openBuilder(fakeApi(entry()));

        fireEvent.click(screen.getAllByRole('button', { name: 'Add block' })[0] as HTMLElement);

        const picker = within(await screen.findByRole('dialog', { name: 'Add block' }));
        expect(picker.getAllByRole('button').map((button) => button.textContent)).toEqual(['Heading']);
        const leftOut = within(picker.getByRole('list', { name: 'Not available here' }));
        expect(leftOut.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Product noteNeeds: Product page', 'Taxon bannerNeeds: Taxon page']);
    });

    it('should offer the block types whose needs a snippet declares', async () => {
        await openBuilder(fakeApi(snippetEntry(['product'])));

        fireEvent.click(screen.getAllByRole('button', { name: 'Add block' })[0] as HTMLElement);

        const picker = within(await screen.findByRole('dialog', { name: 'Add block' }));
        expect(picker.getAllByRole('button').map((button) => button.textContent)).toEqual(['Heading', 'Product note']);
        fireEvent.click(picker.getByRole('button', { name: 'Product note' }));
        expect(within(screen.getByRole('navigation', { name: 'Blocks' })).getByRole('button', { name: 'Product note' })).toBeInTheDocument();
    });
});

describe('the condition kinds', () => {
    it('should offer only those whose needs the entry provides, and name the others with what they need', async () => {
        await openBuilder(fakeApi(entry([blockOf('heading', 'title')])));
        fireEvent.click(within(screen.getByRole('navigation', { name: 'Blocks' })).getByRole('button', { name: 'Heading' }));

        fireEvent.click(pane().getByRole('button', { name: 'Add condition' }));

        const kind = pane().getByRole('combobox', { name: 'Kind' });
        expect(kind).toHaveValue('channel');
        const options = [...kind.querySelectorAll('option')];
        expect(options.map((option) => [option.textContent, option.disabled])).toEqual([
            ['On a product page (Needs: Product page)', true],
            ['Channel', false],
            ['Locale', false],
            ['Date window', false],
            ['Customer group', false],
        ]);
    });

    it('should offer one a snippet declares the context of', async () => {
        await openBuilder(fakeApi(snippetEntry(['product'], [blockOf('heading', 'title')])));
        fireEvent.click(within(screen.getByRole('navigation', { name: 'Blocks' })).getByRole('button', { name: 'Heading' }));

        fireEvent.click(pane().getByRole('button', { name: 'Add condition' }));

        expect(pane().getByRole('combobox', { name: 'Kind' })).toHaveValue('on_product_page');
    });
});

describe("a snippet's context in its settings", () => {
    it('should offer the pages it can be placed on and save the one picked', async () => {
        const api = fakeApi(snippetEntry([]));
        await openBuilder(api, []);

        const shownOn = pane().getByRole('combobox', { name: 'Shown on' });
        expect([...shownOn.querySelectorAll('option')].map((option) => option.textContent)).toEqual(['Any page', 'Product page', 'Taxon page', 'Entry page']);
        expect(shownOn).toHaveValue('');

        fireEvent.change(shownOn, { target: { value: 'product' } });

        expect(shownOn).toHaveValue('product');
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_entry_value', field: 'context', locale: null, value: ['product'] }], 0));
    });

    it("should keep its context and list what's in the way while a placement's slot doesn't provide what it would add", async () => {
        const api = fakeApi(snippetEntry([]));
        await openBuilder(api, [placement('shop.footer', 'Every page: the footer')]);

        const shownOn = pane().getByRole('combobox', { name: 'Shown on' });
        fireEvent.change(shownOn, { target: { value: 'product' } });

        expect(shownOn).toHaveValue('');
        const alert = within(pane().getByRole('alert'));
        expect(alert.getByText("This can't change while these need it:")).toBeInTheDocument();
        expect(alert.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['The placement in "Every page: the footer" doesn\'t provide the host-page context "Product page".']);
        expect(api.saveChanges).not.toHaveBeenCalled();
    });

    it("should keep its context and list what's in the way while a block needs what it would remove", async () => {
        await openBuilder(fakeApi(snippetEntry(['product'], [blockOf('product_note', 'note')])), []);

        const shownOn = pane().getByRole('combobox', { name: 'Shown on' });
        fireEvent.change(shownOn, { target: { value: '' } });

        expect(shownOn).toHaveValue('product');
        expect(within(pane().getByRole('alert')).getAllByRole('listitem').map((item) => item.textContent)).toEqual(['The block "Product note" needs the host-page context "Product page".']);
    });

    it("should take the change back and list what's in the way when the server refuses it", async () => {
        const refused: ValidationError = { block: null, field: 'context', locale: null, message: 'The placement in "Taxon page: the header" doesn\'t provide the host-page context "Product page".' };
        const saveChanges = vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 0, refused: [refused], entry: snippetEntry([]), clashes: [] }));
        await openBuilder(fakeApi(snippetEntry([]), saveChanges), []);

        const shownOn = pane().getByRole('combobox', { name: 'Shown on' });
        fireEvent.change(shownOn, { target: { value: 'product' } });

        await waitFor(() => expect(shownOn).toHaveValue(''));
        expect(within(pane().getByRole('alert')).getAllByRole('listitem').map((item) => item.textContent)).toEqual([refused.message]);
    });

    it('should not be offered for an entry that is not a snippet', async () => {
        await openBuilder(fakeApi(entry()));

        expect(pane().queryByRole('combobox', { name: 'Shown on' })).not.toBeInTheDocument();
    });
});
