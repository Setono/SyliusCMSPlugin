import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { SearchApi, SearchResult } from '../src/search/api';
import { SearchProvider } from '../src/search/context';
import { createSourcesApi } from '../src/sources/api';
import { SourcesProvider, type CountSource } from '../src/sources/context';
import type { SourceDefinition } from '../src/sources/sources';
import type { BlockTypeDefinition, EntryResponse, EntryStatus, FieldSchema, JsonObject, JsonValue, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { blockOf, entry, page, registry, settings } from './fixtures';

const strings = {
    'setono_sylius_cms.builder.items.add_product': 'Add product',
    'setono_sylius_cms.builder.items.change': 'Change…',
    'setono_sylius_cms.builder.items.choose': 'Choose…',
    'setono_sylius_cms.builder.items.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.items.remove': 'Remove',
    'setono_sylius_cms.builder.items.search_products': 'Search products',
    'setono_sylius_cms.builder.items.search_taxons': 'Search taxons',
    'setono_sylius_cms.builder.search.searching': 'Searching…',
    'setono_sylius_cms.builder.search.no_results': 'Nothing found',
    'setono_sylius_cms.builder.sources.count': '%count% items now',
    'setono_sylius_cms.builder.sources.count_one': '1 item now',
    'setono_sylius_cms.builder.sources.filled_by': 'Filled by',
    'setono_sylius_cms.builder.sources.hand_picked': 'Hand-picked',
    'setono_sylius_cms.builder.sources.limit': 'Items to show',
    'setono_sylius_cms.builder.sources.unavailable': 'Unavailable: %source%',
    'setono_sylius_cms.builder.sources.unavailable_help': 'This source isn’t offered any more.',
};

function field(name: string, type: string, label: string, extra: Partial<FieldSchema> = {}) {
    return {
        name,
        schema: { 'x-cms-type': type, 'x-cms-label': label, 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': false, ...extra },
    };
}

const sources: SourceDefinition[] = [
    {
        code: 'products_in_taxon',
        label: 'In taxon',
        needs: [],
        fields: [
            field('taxon', 'taxon', 'Taxon', { 'x-cms-required': true }),
            field('includeChildren', 'toggle', 'Include the taxons below it', { default: false }),
            field('sort', 'choice', 'Sort by', { default: 'position', 'x-cms-options': { choices: { position: 'Position in the taxon', name: 'Name, A to Z' }, multiple: false } }),
        ],
    },
    { code: 'newest_products', label: 'Newest', needs: [], fields: [] },
    { code: 'taxon_children', label: 'Children of a taxon', needs: [], fields: [field('taxon', 'taxon', 'Taxon')] },
];

function productsSchema(offered: string[], limit = 8): FieldSchema {
    return {
        'x-cms-type': 'products',
        'x-cms-label': 'Products',
        'x-cms-help': null,
        'x-cms-required': false,
        'x-cms-translatable': false,
        'x-cms-options': { max: null, sources: offered, limit },
    };
}

const catalog: Record<string, SearchResult[]> = {
    product: [{ ref: 'MUG', name: 'Mug', thumbnail: null }],
    taxon: [{ ref: 'T_SHIRTS', name: 'Category / T-shirts', thumbnail: null }],
};

function fakeSearch(): SearchApi {
    return {
        search: vi.fn(async (kind: string, query: string) => (catalog[kind] ?? []).filter(({ name }) => name.toLowerCase().includes(query.toLowerCase()))),
        find: vi.fn(async (kind: string, refs: readonly string[]) => (catalog[kind] ?? []).filter(({ ref }) => refs.includes(ref))),
    };
}

/** A field whose value lives in the test, as the builder's store keeps it, with every change recorded. */
function Field({ fieldSchema, initial, onChange }: { fieldSchema: FieldSchema; initial?: JsonValue; onChange: (value: JsonValue | null) => void }) {
    const [value, setValue] = useState<JsonValue | undefined>(initial);

    return (
        <FieldRow
            id="field"
            schema={fieldSchema}
            value={value}
            locale="en_US"
            onChange={(next) => {
                onChange(next);
                setValue(next === null || (Array.isArray(next) && next.length === 0) ? undefined : next);
            }}
        />
    );
}

function renderField(fieldSchema: FieldSchema, initial?: JsonValue, count: CountSource | null = null) {
    const onChange = vi.fn();
    render(
        <TranslatorProvider value={createTranslator(strings)}>
            <SearchProvider api={fakeSearch()}>
                <SourcesProvider value={{ sources, count }}>
                    <Field fieldSchema={fieldSchema} initial={initial} onChange={onChange} />
                </SourcesProvider>
            </SearchProvider>
        </TranslatorProvider>,
    );

    return { onChange };
}

function filledBy(): HTMLSelectElement {
    return screen.getByRole('combobox', { name: /Filled by/ });
}

function optionLabels(select: HTMLSelectElement): string[] {
    return within(select)
        .getAllByRole('option')
        .map((option) => option.textContent ?? '');
}

describe('a list field that sources can fill', () => {
    it('should offer hand-picking first, then the sources the field offers, and start hand-picked', () => {
        renderField(productsSchema(['products_in_taxon', 'newest_products']));

        expect(optionLabels(filledBy())).toEqual(['Hand-picked', 'In taxon', 'Newest']);
        expect(filledBy()).toHaveValue('');
        // The field's label names the select, and the hand-picked list keeps its add button
        expect(screen.getByRole('combobox', { name: /Products/ })).toBe(filledBy());
        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add product' })).toBeInTheDocument();
        expect(screen.queryByRole('spinbutton')).not.toBeInTheDocument();
    });

    it('should fill the list with a picked source, showing as many items as the field starts with, and its parameters inline', () => {
        const { onChange } = renderField(productsSchema(['products_in_taxon', 'newest_products'], 4));

        fireEvent.change(filledBy(), { target: { value: 'products_in_taxon' } });

        expect(onChange).toHaveBeenLastCalledWith({ $source: 'products_in_taxon', $params: {}, $limit: 4 });
        expect(screen.getByRole('spinbutton', { name: 'Items to show' })).toHaveValue(4);
        expect(screen.getByRole('button', { name: 'Taxon' })).toHaveTextContent('Choose…');
        expect(screen.getByRole('checkbox', { name: 'Include the taxons below it' })).not.toBeChecked();
        expect(screen.getByRole('combobox', { name: 'Sort by' })).toHaveValue('position');
        expect(screen.queryByRole('button', { name: 'Add product' })).not.toBeInTheDocument();
    });

    it('should store each parameter as it is filled in, and the number of items to show', async () => {
        const { onChange } = renderField(productsSchema(['products_in_taxon']), { $source: 'products_in_taxon', $params: {}, $limit: 8 });

        fireEvent.click(screen.getByRole('button', { name: 'Taxon' }));
        fireEvent.click(await screen.findByRole('option', { name: 'Category / T-shirts' }));
        expect(onChange).toHaveBeenLastCalledWith({ $source: 'products_in_taxon', $params: { taxon: 'T_SHIRTS' }, $limit: 8 });
        await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());

        fireEvent.change(screen.getByRole('combobox', { name: 'Sort by' }), { target: { value: 'name' } });
        expect(onChange).toHaveBeenLastCalledWith({ $source: 'products_in_taxon', $params: { taxon: 'T_SHIRTS', sort: 'name' }, $limit: 8 });

        fireEvent.change(screen.getByRole('spinbutton', { name: 'Items to show' }), { target: { value: '4' } });
        expect(onChange).toHaveBeenLastCalledWith({ $source: 'products_in_taxon', $params: { taxon: 'T_SHIRTS', sort: 'name' }, $limit: 4 });
    });

    it('should not store a number of items below one', () => {
        const { onChange } = renderField(productsSchema(['newest_products']), { $source: 'newest_products', $params: {}, $limit: 8 });
        const limit = screen.getByRole('spinbutton', { name: 'Items to show' });

        fireEvent.change(limit, { target: { value: '' } });
        fireEvent.change(limit, { target: { value: '0' } });

        expect(onChange).not.toHaveBeenCalled();
        fireEvent.blur(limit);
        expect(limit).toHaveValue(8);
    });

    it('should start a picked source afresh, keeping the number of items to show', () => {
        const { onChange } = renderField(productsSchema(['products_in_taxon', 'newest_products']), { $source: 'products_in_taxon', $params: { taxon: 'T_SHIRTS' }, $limit: 3 });

        fireEvent.change(filledBy(), { target: { value: 'newest_products' } });

        expect(onChange).toHaveBeenLastCalledWith({ $source: 'newest_products', $params: {}, $limit: 3 });
    });

    it('should go back to hand-picking with an empty list', () => {
        const { onChange } = renderField(productsSchema(['newest_products']), { $source: 'newest_products', $params: {}, $limit: 8 });

        fireEvent.change(filledBy(), { target: { value: '' } });

        expect(onChange).toHaveBeenLastCalledWith([]);
        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
    });

    it('should show how many items the source yields right now next to Filled by, counted again as its parameters change', async () => {
        const count = vi.fn<CountSource>(async (_source, params) => (params.taxon === 'T_SHIRTS' ? 1 : 12));
        renderField(productsSchema(['products_in_taxon']), { $source: 'products_in_taxon', $params: {}, $limit: 8 }, count);

        expect(await screen.findByText('12 items now')).toBeInTheDocument();
        expect(count).toHaveBeenLastCalledWith('products_in_taxon', {});

        fireEvent.click(screen.getByRole('button', { name: 'Taxon' }));
        fireEvent.click(await screen.findByRole('option', { name: 'Category / T-shirts' }));

        expect(await screen.findByText('1 item now')).toBeInTheDocument();
        expect(count).toHaveBeenLastCalledWith('products_in_taxon', { taxon: 'T_SHIRTS' } satisfies JsonObject);
        // Changing the number of items to show yields the same items, so it isn't counted again
        const calls = count.mock.calls.length;
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Items to show' }), { target: { value: '4' } });
        expect(count).toHaveBeenCalledTimes(calls);
    });

    it('should show no count while it is hand-picked or there is no preview to count in', () => {
        const count = vi.fn<CountSource>(async () => 3);
        renderField(productsSchema(['newest_products']), undefined, count);
        expect(count).not.toHaveBeenCalled();
        expect(screen.queryByText(/items? now/)).not.toBeInTheDocument();
    });

    it('should keep a source the registry no longer gives, flagged, until another way is picked', () => {
        const { onChange } = renderField(productsSchema(['newest_products']), { $source: 'bestsellers', $params: { taxon: 'T_SHIRTS' }, $limit: 8 });

        expect(optionLabels(filledBy())).toEqual(['Hand-picked', 'Unavailable: bestsellers', 'Newest']);
        expect(filledBy()).toHaveValue('bestsellers');
        expect(screen.getByText('This source isn’t offered any more.')).toBeInTheDocument();
        expect(onChange).not.toHaveBeenCalled();
    });

    it('should only hand-pick when the field offers no source, with the add button named by its label', () => {
        renderField(productsSchema([]));

        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Products' })).toHaveTextContent('Add product');
    });
});

describe('the source_count endpoint', () => {
    it('should post the source and its parameters under the preview token, and answer with the count', async () => {
        const fetchFn = vi.fn(async () => new Response(JSON.stringify({ count: 7 }), { status: 200 }));

        await expect(createSourcesApi('/admin/cms/api/sources/count', fetchFn).count('a.token', 'products_in_taxon', { taxon: 'T_SHIRTS' })).resolves.toBe(7);
        expect(fetchFn).toHaveBeenCalledWith('/admin/cms/api/sources/count?_cms_preview=a.token', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ source: 'products_in_taxon', params: { taxon: 'T_SHIRTS' } }),
        });
    });

    it('should fail when it answers without a count', async () => {
        const fetchFn = vi.fn(async () => new Response(JSON.stringify({ error: 'The request has no valid preview token' }), { status: 400 }));

        await expect(createSourcesApi('/count', fetchFn).count('expired', 'newest_products', {})).rejects.toThrow();
    });
});

describe('the builder', () => {
    const productsBlock: BlockTypeDefinition = {
        code: 'products',
        label: 'Products',
        version: 1,
        thumbnail: null,
        disabled: false,
        parents: null,
        children: [],
        styles: [],
        fields: [{ name: 'products', schema: productsSchema(['newest_products']) }],
    };

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
        channels: [
            { code: 'WEB', name: 'Web store' },
            { code: 'B2B', name: 'Wholesale' },
        ],
        customerGroups: [],
    };

    function fakeApi(): BuilderApi {
        let previews = 0;
        const loaded = (): EntryResponse => ({
            entry: entry([blockOf('products', 'list', [], { products: { $source: 'newest_products', $params: {}, $limit: 4 } })]),
            settings: settings({ channels: ['WEB', 'B2B'] }),
            status: 'never_published',
            lockToken: 0,
            editors: [],
        });

        return {
            fetchRegistry: vi.fn(async () => ({
                ...registry,
                blockTypes: [...registry.blockTypes, productsBlock],
                contentTypes: [{ ...page, blocks: [...(page.blocks ?? []), { type: 'products', min: null, max: null }] }],
                sources,
                strings: { ...registry.strings, ...strings },
            })),
            fetchEntry: vi.fn(async () => loaded()),
            saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
            validate: vi.fn(async (): Promise<ValidationError[]> => []),
            publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
            discard: vi.fn(async () => loaded()),
            saveSettings: vi.fn(async () => settings({ channels: ['WEB', 'B2B'] })),
            createPreviewUrl: vi.fn(async ({ channel }: PreviewRequest) => `http://admin.example/en_US/about-us?channel=${channel}&_cms_preview=token-${++previews}`),
            renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
            schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
            cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
            presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        };
    }

    it('should count what a source yields under the token of the page the preview shows, again when the preview changes', async () => {
        const sourcesApi = { count: vi.fn(async (token: string) => (token === 'token-1' ? 5 : 2)) };
        render(<App config={config} api={fakeApi()} sourcesApi={sourcesApi} autosaveDelay={10} />);

        expect(await screen.findByText('5 items now')).toBeInTheDocument();
        expect(sourcesApi.count).toHaveBeenLastCalledWith('token-1', 'newest_products', {});

        fireEvent.change(screen.getByRole('combobox', { name: 'Preview channel' }), { target: { value: 'B2B' } });

        expect(await screen.findByText('2 items now')).toBeInTheDocument();
        expect(sourcesApi.count).toHaveBeenLastCalledWith('token-2', 'newest_products', {});
    });
});
