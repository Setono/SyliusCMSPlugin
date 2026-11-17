import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { SearchApi, SearchResult } from '../src/search/api';
import { SearchProvider } from '../src/search/context';
import type { FieldSchema, JsonObject, JsonValue } from '../src/types';

const strings = {
    'setono_sylius_cms.builder.items.add_product': 'Add product',
    'setono_sylius_cms.builder.items.add_taxon': 'Add taxon',
    'setono_sylius_cms.builder.items.at_most': 'Up to %max%',
    'setono_sylius_cms.builder.items.change': 'Change…',
    'setono_sylius_cms.builder.items.choose': 'Choose…',
    'setono_sylius_cms.builder.items.not_found': '%code% no longer exists, so it isn’t shown.',
    'setono_sylius_cms.builder.items.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.items.remove': 'Remove',
    'setono_sylius_cms.builder.items.search_products': 'Search products',
    'setono_sylius_cms.builder.items.search_taxons': 'Search taxons',
    'setono_sylius_cms.builder.move_up': 'Move up',
    'setono_sylius_cms.builder.move_down': 'Move down',
    'setono_sylius_cms.builder.search.searching': 'Searching…',
    'setono_sylius_cms.builder.search.no_results': 'Nothing found',
    'setono_sylius_cms.builder.search.failed': 'The search failed. Try again.',
};

function schema(type: 'product' | 'products' | 'taxon' | 'taxons', label: string, options?: JsonObject): FieldSchema {
    return {
        ...(type.endsWith('s') ? { type: 'array', items: { type: 'string', minLength: 1 } } : { type: 'string', minLength: 1 }),
        'x-cms-type': type,
        'x-cms-label': label,
        'x-cms-help': null,
        'x-cms-required': false,
        'x-cms-translatable': false,
        ...(options === undefined ? {} : { 'x-cms-options': options }),
    };
}

const productsSchema = schema('products', 'Products', { max: null, sources: true, limit: 8 });

const catalog: Record<string, SearchResult[]> = {
    product: [
        { ref: 'T_SHIRT', name: 'T-shirt', thumbnail: '/media/cache/t-shirt.jpg' },
        { ref: 'MUG', name: 'Mug', thumbnail: null },
        { ref: 'CAP', name: 'Cap', thumbnail: null },
    ],
    taxon: [
        { ref: 'T_SHIRTS', name: 'Category / T-shirts', thumbnail: null },
        { ref: 'MUGS', name: 'Category / Mugs', thumbnail: null },
    ],
};

/** The store's products and taxons, searched by name and looked up by code. */
function fakeSearch(): SearchApi & { search: ReturnType<typeof vi.fn>; find: ReturnType<typeof vi.fn> } {
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
                setValue(next ?? undefined);
            }}
        />
    );
}

function renderField(fieldSchema: FieldSchema, initial?: JsonValue) {
    const onChange = vi.fn();
    const search = fakeSearch();
    render(
        <TranslatorProvider value={createTranslator(strings)}>
            <SearchProvider api={search}>
                <Field fieldSchema={fieldSchema} initial={initial} onChange={onChange} />
            </SearchProvider>
        </TranslatorProvider>,
    );

    return { onChange, search };
}

/** Picks what the search finds by its name, through the field's add or choose button, named by the field's label. */
async function pick(field: string, name: string): Promise<void> {
    fireEvent.click(screen.getByRole('button', { name: field }));
    fireEvent.click(await screen.findByRole('option', { name }));
    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
}

function listed(name: string): (string | null)[] {
    return within(screen.getByRole('list', { name })).getAllByRole('listitem').map((item) => item.textContent);
}

describe('a products field', () => {
    it('should say nothing is chosen, and search products from its add button, named by its label', async () => {
        const { search } = renderField(productsSchema);

        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
        const add = screen.getByRole('button', { name: 'Products' });
        expect(add).toHaveTextContent('Add product');

        fireEvent.click(add);
        expect(await screen.findByRole('combobox', { name: 'Search products' })).toBeInTheDocument();
        await screen.findByRole('option', { name: 'Mug' });
        expect(search.search).toHaveBeenCalledWith('product', '');
    });

    it('should add what is picked to the end of the list, in the order it was picked, by code', async () => {
        const { onChange } = renderField(productsSchema);

        await pick('Products', 'Mug');
        expect(onChange).toHaveBeenLastCalledWith(['MUG']);
        await pick('Products', 'T-shirt');
        expect(onChange).toHaveBeenLastCalledWith(['MUG', 'T_SHIRT']);
        await pick('Products', 'Cap');
        expect(onChange).toHaveBeenLastCalledWith(['MUG', 'T_SHIRT', 'CAP']);

        expect(listed('Products')).toEqual([expect.stringContaining('Mug'), expect.stringContaining('T-shirt'), expect.stringContaining('Cap')]);
    });

    it('should name what it holds, looked up in one request', async () => {
        const { search } = renderField(productsSchema, ['CAP', 'MUG']);

        await screen.findByText('Cap');
        expect(listed('Products')).toEqual([expect.stringContaining('Cap'), expect.stringContaining('Mug')]);
        expect(search.find).toHaveBeenCalledTimes(1);
        expect(search.find).toHaveBeenCalledWith('product', ['CAP', 'MUG']);
    });

    it('should not add what it holds already', async () => {
        const { onChange } = renderField(productsSchema, ['MUG']);
        await screen.findByText('Mug');

        await pick('Products', 'Mug');

        expect(onChange).not.toHaveBeenCalled();
        expect(listed('Products')).toHaveLength(1);
    });

    it('should move a product up and down, and remove it', async () => {
        const { onChange } = renderField(productsSchema, ['MUG', 'T_SHIRT', 'CAP']);
        const list = within(await screen.findByRole('list', { name: 'Products' }));
        await screen.findByText('Cap');

        fireEvent.click(within(list.getAllByRole('listitem')[2] as HTMLElement).getByRole('button', { name: 'Move up' }));
        expect(onChange).toHaveBeenLastCalledWith(['MUG', 'CAP', 'T_SHIRT']);
        fireEvent.click(within(list.getAllByRole('listitem')[0] as HTMLElement).getByRole('button', { name: 'Move down' }));
        expect(onChange).toHaveBeenLastCalledWith(['CAP', 'MUG', 'T_SHIRT']);
        expect(within(list.getAllByRole('listitem')[0] as HTMLElement).getByRole('button', { name: 'Move up' })).toBeDisabled();
        expect(within(list.getAllByRole('listitem')[2] as HTMLElement).getByRole('button', { name: 'Move down' })).toBeDisabled();

        fireEvent.click(within(list.getAllByRole('listitem')[1] as HTMLElement).getByRole('button', { name: 'Remove' }));
        expect(onChange).toHaveBeenLastCalledWith(['CAP', 'T_SHIRT']);
    });

    it('should hold no more than its most, and say how many it holds at most', async () => {
        renderField(schema('products', 'Products', { max: 2, sources: true, limit: 8 }), ['MUG', 'CAP']);

        await screen.findByText('Cap');
        expect(screen.getByRole('button', { name: 'Products' })).toBeDisabled();
        expect(screen.getByText('Up to 2')).toBeInTheDocument();
    });

    it('should keep a product that no longer exists in the list, flagged by its code, so it can be removed', async () => {
        renderField(productsSchema, ['GONE', 'MUG']);

        expect(await screen.findByText('GONE no longer exists, so it isn’t shown.')).toBeInTheDocument();
        expect(listed('Products')).toHaveLength(2);
    });
});

describe('a taxons field', () => {
    it('should search taxons and add what is picked by code', async () => {
        const { onChange, search } = renderField(schema('taxons', 'Categories', { max: null, sources: true, limit: 8 }));

        expect(screen.getByRole('button', { name: 'Categories' })).toHaveTextContent('Add taxon');
        await pick('Categories', 'Category / Mugs');

        expect(search.search).toHaveBeenCalledWith('taxon', '');
        expect(onChange).toHaveBeenLastCalledWith(['MUGS']);
    });
});

describe('a product field', () => {
    it('should choose one product, show its name, change it and remove it', async () => {
        const { onChange } = renderField(schema('product', 'Featured product'));

        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Featured product' })).toHaveTextContent('Choose…');
        await pick('Featured product', 'Mug');
        expect(onChange).toHaveBeenLastCalledWith('MUG');
        expect(screen.getByText('Mug')).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Featured product' })).toHaveTextContent('Change…');
        await pick('Featured product', 'Cap');
        expect(onChange).toHaveBeenLastCalledWith('CAP');

        fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
        expect(onChange).toHaveBeenLastCalledWith(null);
        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
    });

    it('should name the product it holds, and flag one that no longer exists', async () => {
        renderField(schema('product', 'Featured product'), 'GONE');

        expect(await screen.findByText('GONE no longer exists, so it isn’t shown.')).toBeInTheDocument();
    });
});

describe('a taxon field', () => {
    it('should search taxons and name the one it holds', async () => {
        const { search } = renderField(schema('taxon', 'Category'), 'T_SHIRTS');

        expect(await screen.findByText('Category / T-shirts')).toBeInTheDocument();
        await pick('Category', 'Category / Mugs');
        expect(search.search).toHaveBeenCalledWith('taxon', '');
    });
});
