import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import { offeredDisplayChoices } from '../src/entriesBlock';
import type { SearchResult } from '../src/search/api';
import { createSourcesApi } from '../src/sources/api';
import { SourcesProvider } from '../src/sources/context';
import { fitsEntry, paramSchema, withSourceParam, type SourceDefinition } from '../src/sources/sources';
import type { BlockTypeDefinition, ContentTypeDefinition, Entry, EntryResponse, EntryStatus, FieldSchema, JsonValue, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { blockOf, entry, fakeSearch, page, registry, renderField, settings } from './fixtures';

/*
 * The Entries block: the entry sources From a collection, In this category and Related, the categories From a
 * collection lists by the collection chosen, and the displays the block offers for what it lists. See "The Entries
 * block" in docs/spec/collections.md and "Sources" in docs/spec/extending.md
 */

const strings = {
    'setono_sylius_cms.builder.items.add_entry': 'Add entry',
    'setono_sylius_cms.builder.items.choose': 'Choose…',
    'setono_sylius_cms.builder.items.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.items.search_entries': 'Search entries',
    'setono_sylius_cms.builder.search.searching': 'Searching…',
    'setono_sylius_cms.builder.search.no_results': 'Nothing found',
    'setono_sylius_cms.builder.sources.filled_by': 'Filled by',
    'setono_sylius_cms.builder.sources.hand_picked': 'Hand-picked',
    'setono_sylius_cms.builder.sources.limit': 'Items to show',
};

function schema(type: string, label: string, extra: Partial<FieldSchema> = {}): FieldSchema {
    return { 'x-cms-type': type, 'x-cms-label': label, 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': false, ...extra };
}

const collection = schema('choice', 'Collection', { 'x-cms-required': true, 'x-cms-options': { choices: { blog_post: 'Blog', faq_item: 'FAQ', store_location: 'Store locations' }, multiple: false } });
const categories = schema('entries', 'Categories', { 'x-cms-options': { max: null, sources: [], limit: 8, contentType: null, categoriesOf: 'contentType' } });

const sources: SourceDefinition[] = [
    { code: 'entries_from_collection', label: 'From a collection', needs: [], entryContentTypes: null, fields: [{ name: 'contentType', schema: collection }, { name: 'categories', schema: categories }] },
    { code: 'entries_in_this_category', label: 'In this category', needs: ['entry'], entryContentTypes: ['blog_category', 'faq_category'], fields: [] },
    { code: 'related_entries', label: 'Related', needs: ['entry'], entryContentTypes: ['blog_post', 'faq_item'], fields: [] },
];

const categoryTypes = { blog_post: 'blog_category', faq_item: 'faq_category', store_location: null, page: null };

const entriesSchema = schema('entries', 'Entries', { 'x-cms-options': { max: null, sources: ['entries_from_collection', 'entries_in_this_category', 'related_entries'], limit: 9, contentType: null } });

const catalog: Record<string, SearchResult[]> = { entry: [{ ref: '0193a5be-0000-7000-8000-0000000000c1', name: 'News', thumbnail: null }] };

function renderEntriesField(initial: JsonValue | undefined, entryContentType: string | null | undefined) {
    const search = fakeSearch(catalog);
    const rendered = renderField(entriesSchema, {
        initial,
        strings,
        search,
        providers: (field) => <SourcesProvider value={{ sources, count: null, provided: ['entry'], entryContentType, categoryTypes }}>{field}</SourcesProvider>,
    });

    return { ...rendered, search };
}

function optionLabels(select: HTMLElement): string[] {
    return within(select)
        .getAllByRole('option')
        .map((option) => option.textContent ?? '');
}

describe('the entry sources', () => {
    it('should offer a source that fits only some content types as the entry only in entries of them', () => {
        expect(fitsEntry(sources[1]!, 'blog_category')).toBe(true);
        expect(fitsEntry(sources[1]!, 'page')).toBe(false);
        expect(fitsEntry(sources[0]!, 'page')).toBe(true);
        // A snippet's entry can be of any content type, and outside the builder there is no entry
        expect(fitsEntry(sources[1]!, null)).toBe(true);
        expect(fitsEntry(sources[1]!, undefined)).toBe(true);
    });

    it('should offer In this category in a blog category, and Related in a blog post', () => {
        renderEntriesField(undefined, 'blog_category');
        expect(optionLabels(screen.getByRole('combobox', { name: /Filled by/ }))).toEqual(['Hand-picked', 'From a collection', 'In this category']);
    });

    it('should offer neither In this category nor Related in a page', () => {
        renderEntriesField(undefined, 'page');
        expect(optionLabels(screen.getByRole('combobox', { name: /Filled by/ }))).toEqual(['Hand-picked', 'From a collection']);
    });
});

describe("From a collection's categories", () => {
    it('should search the categories of the chosen collection, and be left out until a collection with categories is chosen', () => {
        const categoriesOfBlog = paramSchema(categories, { contentType: 'blog_post' }, categoryTypes);

        expect(categoriesOfBlog?.['x-cms-options']).toEqual({ max: null, sources: [], limit: 8, contentType: 'blog_category', categoriesOf: 'contentType' });
        expect(paramSchema(categories, {}, categoryTypes)).toBeNull();
        expect(paramSchema(categories, { contentType: 'store_location' }, categoryTypes)).toBeNull();
        expect(paramSchema(collection, {}, categoryTypes)).toBe(collection);
    });

    it('should be cleared when the collection changes', () => {
        const source = { $source: 'entries_from_collection', $params: { contentType: 'blog_post', categories: ['0193a5be-0000-7000-8000-0000000000c1'] }, $limit: 9 };
        const fields = sources[0]!.fields;

        expect(withSourceParam(source, 'contentType', 'faq_item', fields)).toEqual({ $source: 'entries_from_collection', $params: { contentType: 'faq_item' }, $limit: 9 });
        expect(withSourceParam(source, 'categories', [], fields)).toEqual({ $source: 'entries_from_collection', $params: { contentType: 'blog_post' }, $limit: 9 });
    });

    it('should pick the categories of the chosen collection in the builder, and clear them when another collection is chosen', async () => {
        const { onChange, search } = renderEntriesField({ $source: 'entries_from_collection', $params: { contentType: 'blog_post' }, $limit: 9 }, 'page');

        fireEvent.click(screen.getByRole('button', { name: 'Categories' }));
        await waitFor(() => expect(search.search).toHaveBeenCalledWith('entry', '', { contentType: 'blog_category' }));
        fireEvent.click(await screen.findByRole('option', { name: 'News' }));
        expect(onChange).toHaveBeenLastCalledWith({ $source: 'entries_from_collection', $params: { contentType: 'blog_post', categories: ['0193a5be-0000-7000-8000-0000000000c1'] }, $limit: 9 });

        fireEvent.change(screen.getByRole('combobox', { name: 'Collection' }), { target: { value: 'store_location' } });
        expect(onChange).toHaveBeenLastCalledWith({ $source: 'entries_from_collection', $params: { contentType: 'store_location' }, $limit: 9 });
        expect(screen.queryByRole('button', { name: 'Categories' })).not.toBeInTheDocument();
    });
});

describe("the Entries block's display", () => {
    const display = schema('choice', 'Display', { default: 'grid', 'x-cms-options': { choices: { grid: 'Grid', carousel: 'Carousel', accordion: 'Accordion' }, multiple: false } });

    it('should offer only the displays offered for what the block lists, keeping the one it holds', () => {
        expect(offeredDisplayChoices(display, ['accordion'], 'accordion')['x-cms-options']).toEqual({ choices: { accordion: 'Accordion' }, multiple: false });
        expect(offeredDisplayChoices(display, ['accordion'], undefined)['x-cms-options']).toEqual({ choices: { grid: 'Grid', accordion: 'Accordion' }, multiple: false });
        expect(offeredDisplayChoices(display, null, 'grid')).toBe(display);
    });

    const entriesBlock: BlockTypeDefinition = {
        code: 'entries',
        label: 'Entries',
        version: 1,
        thumbnail: null,
        disabled: false,
        parents: null,
        children: [],
        styles: [],
        fields: [
            { name: 'entries', schema: entriesSchema },
            { name: 'display', schema: display },
        ],
    };

    const listPage: ContentTypeDefinition = { ...page, categoryType: null, blocks: [...(page.blocks ?? []), { type: 'entries', min: null, max: null }] };

    const config: BuilderConfig = {
        entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
        locale: 'en_US',
        block: 'list',
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

    function fakeApi(listed: Entry): BuilderApi {
        const loaded = (): EntryResponse => ({ entry: listed, settings: settings({ channels: ['WEB'] }), status: 'never_published', lockToken: 0, editors: [] });

        return {
            fetchRegistry: vi.fn(async () => ({ ...registry, blockTypes: [...registry.blockTypes, entriesBlock], contentTypes: [listPage], sources, strings: { ...registry.strings, ...strings } })),
            fetchEntry: vi.fn(async () => loaded()),
            saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
            validate: vi.fn(async (): Promise<ValidationError[]> => []),
            publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
            discard: vi.fn(async () => loaded()),
            saveSettings: vi.fn(async () => settings({ channels: ['WEB'] })),
            createPreviewUrl: vi.fn(async (_request: PreviewRequest) => 'http://admin.example/en_US/blog?_cms_preview=token-1'),
            renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
            schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
            cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
            presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        };
    }

    it('should offer the displays the server offers for what the block lists, under the preview token', async () => {
        const listed = { $source: 'entries_from_collection', $params: { contentType: 'faq_item' }, $limit: 9 };
        const sourcesApi = { count: vi.fn(async () => 3), displays: vi.fn(async () => ['accordion']) };
        render(<App config={config} api={fakeApi(entry([blockOf('entries', 'list', [], { entries: listed, display: 'accordion' })]))} sourcesApi={sourcesApi} autosaveDelay={10} />);

        const select = await screen.findByRole('combobox', { name: 'Display' });
        await waitFor(() => expect(optionLabels(select)).toEqual(['Accordion']));
        expect(sourcesApi.displays).toHaveBeenLastCalledWith('token-1', listed);
    });
});

describe('the card_displays endpoint', () => {
    it("should post the entries field's value under the preview token, and answer with the displays", async () => {
        const fetchFn = vi.fn(async () => new Response(JSON.stringify({ displays: ['grid', 'carousel'] }), { status: 200 }));
        const listed = { $source: 'entries_from_collection', $params: { contentType: 'blog_post' }, $limit: 9 };

        await expect(createSourcesApi('/count', fetchFn, '/admin/cms/api/cards/displays').displays?.('a.token', listed)).resolves.toEqual(['grid', 'carousel']);
        expect(fetchFn).toHaveBeenCalledWith('/admin/cms/api/cards/displays?_cms_preview=a.token', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ entries: listed }),
        });
    });

    it('should not ask without the endpoint', () => {
        expect(createSourcesApi('/count').displays).toBeUndefined();
    });
});
