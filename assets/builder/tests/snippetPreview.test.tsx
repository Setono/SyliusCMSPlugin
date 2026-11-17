import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig, SnippetPlacement } from '../src/config';
import { createPreviewStore } from '../src/preview';
import { createSnippetPreviewApi, startingPlacement, type SnippetPreviewApi, type SnippetPreviewState } from '../src/snippetPreview';
import type { ContentTypeDefinition, Entry, EntryResponse, EntryStatus, PresenceResponse, Registry, SaveResponse, ValidationError } from '../src/types';
import { block, entry, registry, settings, strings } from './fixtures';

const snippet: ContentTypeDefinition = { code: 'snippet', label: 'Snippet', blocks: [{ type: 'heading', min: null, max: null }], provides: null, layouts: [] };

const snippetStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.context_any_page': 'Any page',
    'setono_sylius_cms.builder.context_help': 'The pages it can be placed on.',
    'setono_sylius_cms.builder.context': 'Shown on',
    'setono_sylius_cms.builder.snippet_preview.in_scope': 'In the page scope',
    'setono_sylius_cms.builder.snippet_preview.label': 'Snippet preview',
    'setono_sylius_cms.builder.snippet_preview.no_placements': 'Not placed yet, so it shows on a plain page',
    'setono_sylius_cms.builder.snippet_preview.outside_scope': "%sample% is outside the placement's page scope, so shoppers don't see the snippet on its page.",
    'setono_sylius_cms.builder.snippet_preview.placement': 'Placement',
    'setono_sylius_cms.builder.snippet_preview.plain_page': 'A plain page',
    'setono_sylius_cms.builder.snippet_preview.sample': 'Sample',
    'setono_sylius_cms.builder.snippet_preview.search_samples': 'Search samples',
    'setono_sylius_cms.builder.snippet_preview.shown_instead': 'Shoppers like this see %snippets% here instead, as the slot is capped.',
};

const snippetRegistry: Registry = {
    ...registry,
    contentTypes: [...registry.contentTypes, snippet],
    contextKeys: [
        { key: 'product', label: 'Product page' },
        { key: 'taxon', label: 'Taxon page' },
    ],
    strings: snippetStrings,
};

const placements: SnippetPlacement[] = [
    { uuid: 'removed', slot: 'shop.gone', label: 'shop.gone (no longer in the code)', removed: true },
    { uuid: 'after-price', slot: 'product.after_price', label: 'Product page: under the price', removed: false },
    { uuid: 'sidebar', slot: 'product.sidebar', label: 'Product page: the sidebar', removed: false },
];

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
    placements,
};

function snippetEntry(context: string[]): Entry {
    const blocks = [block('a', { text: { en_US: 'Rinse after use' } })];

    return { ...entry(blocks), contentType: 'snippet', fields: { context }, tree: { layout: null, blocks } };
}

/** Answers each preview request with a new URL that says where it shows the snippet, with the token's number. */
function previewUrls() {
    let count = 0;

    return vi.fn(async ({ placement, sample }: PreviewRequest) => `http://admin.example/en_US/products/x?placement=${placement ?? 'none'}&sample=${sample ?? 'start'}&_cms_preview=${++count}`);
}

function fakeApi(context: string[] = ['product']) {
    return {
        fetchRegistry: vi.fn(async () => snippetRegistry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: snippetEntry(context), settings: settings({ channels: ['WEB'] }), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(() => Promise.reject(new Error('Not discarded in this test'))),
        saveSettings: vi.fn(async () => settings({ channels: ['WEB'] })),
        createPreviewUrl: previewUrls(),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    };
}

/** What the top bar shows for each token: the sample object Mug, inside the scope, unless the state says otherwise. */
function fakeSnippetPreview(states: Record<string, Partial<SnippetPreviewState>> = {}): SnippetPreviewApi & { state: ReturnType<typeof vi.fn>; samples: ReturnType<typeof vi.fn> } {
    return {
        state: vi.fn(async (token: string) => ({
            sample: { key: 'product', ref: 'MUG', name: 'Mug', matches: true },
            shownInstead: null,
            ...states[token],
        })),
        samples: vi.fn(async () => [
            { ref: 'MUG', name: 'Mug', thumbnail: null, matches: true },
            { ref: 'T_SHIRT', name: 'T-shirt', thumbnail: null, matches: false },
        ]),
    };
}

async function openBuilder(api: BuilderApi, snippetPreviewApi: SnippetPreviewApi, builderConfig: BuilderConfig = config) {
    render(<App config={builderConfig} api={api} snippetPreviewApi={snippetPreviewApi} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function preview(): HTMLIFrameElement {
    return screen.getByTitle('Preview') as HTMLIFrameElement;
}

function placement(): HTMLSelectElement {
    return screen.getByRole('combobox', { name: 'Placement' }) as HTMLSelectElement;
}

describe('Snippet preview', () => {
    it("should show the snippet's context and preview it in its first placement in a declared slot, with the sample object it starts with", async () => {
        const api = fakeApi();
        const snippetPreview = fakeSnippetPreview();
        await openBuilder(api, snippetPreview);

        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('placement=after-price&sample=start&_cms_preview=1')));
        expect(api.createPreviewUrl).toHaveBeenCalledExactlyOnceWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: null, placement: 'after-price', sample: null });
        expect(screen.getByTitle('The pages it can be placed on.')).toHaveTextContent('Product page');
        expect(Array.from(placement().options, (option) => [option.value, option.text, option.disabled])).toEqual([
            ['removed', 'shop.gone (no longer in the code)', true],
            ['after-price', 'Product page: under the price', false],
            ['sidebar', 'Product page: the sidebar', false],
            ['', 'A plain page', false],
        ]);
        expect(placement()).toHaveValue('after-price');
        expect(await screen.findByRole('button', { name: 'Sample: Mug' })).toBeInTheDocument();
        expect(snippetPreview.state).toHaveBeenCalledWith('1');
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('should reload the preview in the placement picked, with the sample object it starts with', async () => {
        const api = fakeApi();
        await openBuilder(api, fakeSnippetPreview());
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('_cms_preview=1')));

        fireEvent.change(placement(), { target: { value: 'sidebar' } });

        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('placement=sidebar&sample=start&_cms_preview=2')));
    });

    it('should mark the sample objects inside the page scope, and reload the preview on the page of the one picked, warning when it is outside the scope', async () => {
        const api = fakeApi();
        const snippetPreview = fakeSnippetPreview({ 2: { sample: { key: 'product', ref: 'T_SHIRT', name: 'T-shirt', matches: false } } });
        await openBuilder(api, snippetPreview);

        fireEvent.click(await screen.findByRole('button', { name: 'Sample: Mug' }));
        const picker = within(await screen.findByRole('dialog', { name: 'Sample' }));
        expect((await picker.findAllByRole('option')).map((option) => option.textContent)).toEqual(['MugIn the page scope', 'T-shirt']);
        expect(snippetPreview.samples).toHaveBeenCalledWith('1', '');
        fireEvent.click(picker.getByRole('option', { name: /T-shirt/ }));

        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('placement=after-price&sample=T_SHIRT&_cms_preview=2')));
        expect(await screen.findByRole('alert')).toHaveTextContent("T-shirt is outside the placement's page scope, so shoppers don't see the snippet on its page.");
        expect(screen.getByRole('button', { name: 'Sample: T-shirt' })).toBeInTheDocument();
    });

    it('should say which snippets shoppers like these see where the placement loses its capped slot', async () => {
        await openBuilder(fakeApi([]), fakeSnippetPreview({ 1: { sample: null, shownInstead: ['Free shipping'] } }));

        expect(await screen.findByText('Shoppers like this see Free shipping here instead, as the slot is capped.')).toBeInTheDocument();
        expect(screen.getByTitle('The pages it can be placed on.')).toHaveTextContent('Any page');
        expect(screen.queryByRole('button', { name: /^Sample/ })).not.toBeInTheDocument();
    });

    it('should preview a snippet without placements on a plain page', async () => {
        const api = fakeApi();
        await openBuilder(api, fakeSnippetPreview(), { ...config, placements: [] });

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledExactlyOnceWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: null, placement: null, sample: null }));
        expect(within(screen.getByRole('group', { name: 'Snippet preview' })).getByText('Not placed yet, so it shows on a plain page')).toBeInTheDocument();
        expect(screen.queryByRole('combobox', { name: 'Placement' })).not.toBeInTheDocument();
    });

    it('should show nothing of it for an entry that is not a snippet', async () => {
        const api = { ...fakeApi(), fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a')]), settings: settings({ channels: ['WEB'] }), status: 'never_published', lockToken: 0, editors: [] })) };
        await openBuilder(api, fakeSnippetPreview(), { ...config, placements: null });

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledExactlyOnceWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: null }));
        expect(screen.queryByRole('combobox', { name: 'Placement' })).not.toBeInTheDocument();
        expect(screen.queryByRole('group', { name: 'Snippet preview' })).not.toBeInTheDocument();
    });
});

describe('The preview of a snippet', () => {
    it('should start in its first placement in a slot the code declares, or a plain page', () => {
        expect(startingPlacement(placements)).toBe('after-price');
        expect(startingPlacement([placements[0]!])).toBeNull();
        expect(startingPlacement([])).toBeNull();
        expect(startingPlacement(null)).toBeNull();
    });

    it('should forget the sample object picked when it shows another placement', () => {
        const store = createPreviewStore([{ code: 'WEB', name: 'Web store' }], undefined, 'after-price');
        store.getState().setSample('T_SHIRT');

        store.getState().setPlacement('sidebar');

        expect(store.getState()).toMatchObject({ placement: 'sidebar', sample: null });
    });
});

describe('The snippet preview endpoints', () => {
    it("should ask for what the top bar shows and the sample objects under the preview's token", async () => {
        const fetchFn = vi.fn(async (url: string) =>
            Response.json(
                url.startsWith('/preview/snippet')
                    ? { sample: { key: 'product', ref: 'MUG', name: 'Mug', matches: false }, shownInstead: null }
                    : [{ ref: 'MUG', name: 'Mug', thumbnail: null, matches: true }],
            ),
        );
        const api = createSnippetPreviewApi({ snippet: '/preview/snippet', samples: '/preview/samples' }, fetchFn);

        expect(await api.state('a.b')).toEqual({ sample: { key: 'product', ref: 'MUG', name: 'Mug', matches: false }, shownInstead: null });
        expect(await api.samples('a.b', 'mu g')).toEqual([{ ref: 'MUG', name: 'Mug', thumbnail: null, matches: true }]);
        expect(fetchFn.mock.calls.map(([url]) => url)).toEqual(['/preview/snippet?_cms_preview=a.b', '/preview/samples?q=mu+g&_cms_preview=a.b']);
    });

    it('should refuse an answer of another shape', async () => {
        const api = createSnippetPreviewApi({ snippet: '/preview/snippet', samples: '/preview/samples' }, async () => Response.json({ sample: 'MUG' }));

        await expect(api.state('a.b')).rejects.toThrow("/preview/snippet didn't answer with the snippet's preview.");
        await expect(api.samples('a.b', '')).rejects.toThrow("/preview/samples didn't answer with sample objects.");
    });
});
