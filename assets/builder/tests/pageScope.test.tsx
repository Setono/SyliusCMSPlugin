import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { PageScopesConfig, PlacementScopeConfig } from '../src/pageScope/config';
import { PageScopeEditor } from '../src/pageScope/PageScopeEditor';
import type { FieldSchema, VisibilityCondition } from '../src/types';
import { fakeSearch } from './fixtures';

function list(label: string, type: 'products' | 'taxons' | 'entries'): FieldSchema {
    return {
        type: 'array',
        items: { type: 'string', minLength: 1 },
        'x-cms-type': type,
        'x-cms-label': label,
        'x-cms-help': null,
        'x-cms-required': true,
        'x-cms-translatable': false,
        'x-cms-options': { max: null, sources: [], limit: 8 },
    };
}

const toggle: FieldSchema = {
    type: 'boolean',
    'x-cms-type': 'toggle',
    'x-cms-label': 'Include the taxons below them',
    'x-cms-help': null,
    'x-cms-required': false,
    'x-cms-translatable': false,
};

const oneOf = { matches: 'is one of', matchesNot: 'is not one of' };

const config: PageScopesConfig = {
    conditionKinds: [
        { code: 'product', label: 'Product', ...oneOf, needs: ['product'], fields: [{ name: 'products', schema: list('Products', 'products') }] },
        {
            code: 'product_in_taxon',
            label: 'Product in taxon',
            ...oneOf,
            needs: ['product'],
            fields: [
                { name: 'taxons', schema: list('Taxons', 'taxons') },
                { name: 'includeChildren', schema: toggle },
            ],
        },
        { code: 'taxon', label: 'Taxon', ...oneOf, needs: ['taxon'], fields: [{ name: 'taxons', schema: list('Taxons', 'taxons') }] },
        { code: 'entry', label: 'Entry', ...oneOf, needs: ['entry'], fields: [{ name: 'entries', schema: list('Entries', 'entries') }] },
    ],
    contextKeys: [
        { key: 'product', label: 'Product page' },
        { key: 'taxon', label: 'Taxon page' },
        { key: 'entry', label: 'Entry page' },
    ],
    endpoints: { search: '/admin/cms/api/search/__kind__' },
    locale: 'en_US',
    strings: {
        'setono_sylius_cms.builder.add_condition': 'Add condition',
        'setono_sylius_cms.builder.condition': 'Condition %number%',
        'setono_sylius_cms.builder.condition_kind': 'Kind',
        'setono_sylius_cms.builder.condition_match': 'Matches',
        'setono_sylius_cms.builder.needs_context': 'Needs: %context%',
        'setono_sylius_cms.builder.remove_condition': 'Remove condition',
        'setono_sylius_cms.builder.unknown_condition_kind': 'Unknown condition: %kind%',
        'setono_sylius_cms.builder.items.add_entry': 'Add entry',
        'setono_sylius_cms.builder.items.add_taxon': 'Add taxon',
        'setono_sylius_cms.builder.items.nothing_picked': 'Nothing chosen',
        'setono_sylius_cms.builder.items.remove': 'Remove',
        'setono_sylius_cms.builder.items.search_entries': 'Search entries',
        'setono_sylius_cms.builder.items.search_taxons': 'Search taxons',
        'setono_sylius_cms.builder.page_scope.cancel': 'Cancel',
        'setono_sylius_cms.builder.page_scope.description': 'Limit the placement in "%slot%" to some of the pages the slot appears on.',
        'setono_sylius_cms.builder.page_scope.edit': 'Edit page scope',
        'setono_sylius_cms.builder.page_scope.every_page': 'No conditions: it shows on every page the slot appears on.',
        'setono_sylius_cms.builder.page_scope.save': 'Save page scope',
        'setono_sylius_cms.builder.page_scope.title': 'Page scope',
        'setono_sylius_cms.builder.page_scope.unknown_condition_kind_help': "The code no longer has this kind of condition, so the placement doesn't show.",
    },
};

function placement(scope: VisibilityCondition[] = [], kinds: string[] = ['product', 'product_in_taxon']): PlacementScopeConfig {
    return { action: '/admin/cms/entry/snippet/placements/placement/page-scope', csrfToken: 'token', slot: 'Product page: under the price', scope, kinds };
}

const catalog = {
    taxon: [
        { ref: 'SHOES', name: 'Category / Shoes', thumbnail: null },
        { ref: 'T_SHIRTS', name: 'Category / T-shirts', thumbnail: null },
    ],
    entry: [{ ref: '0193a5be-0000-7000-8000-000000000001', name: 'About us', thumbnail: null }],
};

function renderEditor(scoped: PlacementScopeConfig = placement()) {
    const search = fakeSearch(catalog);
    render(<PageScopeEditor config={config} placement={scoped} search={search} />);
    fireEvent.click(screen.getByRole('button', { name: 'Edit page scope' }));

    return { dialog: screen.getByRole('dialog', { name: 'Page scope' }), search };
}

/** The scope the form posts, as JSON */
function posted(dialog: HTMLElement): unknown {
    const input = dialog.querySelector<HTMLInputElement>('input[name="scope"]');

    return JSON.parse(input?.value ?? 'null');
}

describe("a placement's page scope editor", () => {
    it("should post an empty scope to the placement's form action with the page's CSRF token, which shows on every page", () => {
        const { dialog } = renderEditor();

        expect(dialog).toHaveTextContent('Limit the placement in "Product page: under the price" to some of the pages the slot appears on.');
        expect(within(dialog).getByText('No conditions: it shows on every page the slot appears on.')).toBeInTheDocument();
        const form = dialog.querySelector('form');
        expect(form).toHaveAttribute('method', 'post');
        expect(form).toHaveAttribute('action', '/admin/cms/entry/snippet/placements/placement/page-scope');
        expect(dialog.querySelector('input[name="_csrf_token"]')).toHaveValue('token');
        expect(posted(dialog)).toEqual([]);
        expect(within(dialog).getByRole('button', { name: 'Save page scope' })).toHaveAttribute('type', 'submit');
    });

    it('should scope the placement to products in a taxon, including the taxons below it', async () => {
        const { dialog } = renderEditor();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Add condition' }));
        const condition = within(dialog).getByRole('group', { name: 'Condition 1' });
        fireEvent.change(within(condition).getByLabelText('Kind'), { target: { value: 'product_in_taxon' } });
        fireEvent.click(within(condition).getByRole('button', { name: 'Taxons' }));
        fireEvent.click(await screen.findByRole('option', { name: 'Category / Shoes' }));
        await waitFor(() => expect(posted(dialog)).toEqual([{ kind: 'product_in_taxon', negate: false, params: { taxons: ['SHOES'] } }]));

        fireEvent.click(within(condition).getByRole('checkbox', { name: 'Include the taxons below them' }));
        fireEvent.change(within(condition).getByLabelText('Matches'), { target: { value: 'not' } });

        expect(posted(dialog)).toEqual([{ kind: 'product_in_taxon', negate: true, params: { taxons: ['SHOES'], includeChildren: true } }]);
    });

    it('should offer only the kinds whose needs the slot provides, naming what the others need', () => {
        const { dialog } = renderEditor();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Add condition' }));
        const kind = within(within(dialog).getByRole('group', { name: 'Condition 1' })).getByLabelText<HTMLSelectElement>('Kind');

        expect(kind).toHaveValue('product');
        expect([...kind.options].map((option) => [option.textContent, option.disabled])).toEqual([
            ['Product', false],
            ['Product in taxon', false],
            ['Taxon (Needs: Taxon page)', true],
            ['Entry (Needs: Entry page)', true],
        ]);
    });

    it('should not offer to add a condition in a slot that provides no context a kind needs', () => {
        const { dialog } = renderEditor(placement([{ kind: 'taxon', negate: false, params: { taxons: ['SHOES'] } }], []));

        expect(within(dialog).queryByRole('button', { name: 'Add condition' })).not.toBeInTheDocument();
        fireEvent.click(within(dialog).getByRole('button', { name: 'Remove condition' }));

        expect(posted(dialog)).toEqual([]);
    });

    it('should pick entries for an entry condition from the entries search', async () => {
        const { dialog } = renderEditor(placement([], ['entry']));

        fireEvent.click(within(dialog).getByRole('button', { name: 'Add condition' }));
        fireEvent.click(within(dialog).getByRole('button', { name: 'Entries' }));
        fireEvent.click(await screen.findByRole('option', { name: 'About us' }));

        await waitFor(() => expect(posted(dialog)).toEqual([{ kind: 'entry', negate: false, params: { entries: ['0193a5be-0000-7000-8000-000000000001'] } }]));
    });

    it('should keep a condition of a kind the code no longer has, say so, and let it be removed', () => {
        const { dialog } = renderEditor(placement([{ kind: 'brand', negate: false, params: [] as unknown as Record<string, never> }]));

        expect(within(dialog).getByLabelText('Kind')).toHaveValue('brand');
        expect(within(dialog).getByText("The code no longer has this kind of condition, so the placement doesn't show.")).toBeInTheDocument();
        expect(posted(dialog)).toEqual([{ kind: 'brand', negate: false, params: {} }]);
    });

    it('should start again from the saved scope when it opens again after Cancel', () => {
        const { dialog } = renderEditor(placement([{ kind: 'product', negate: true, params: { products: ['T_SHIRT'] } }]));

        fireEvent.click(within(dialog).getByRole('button', { name: 'Remove condition' }));
        expect(posted(dialog)).toEqual([]);
        fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
        fireEvent.click(screen.getByRole('button', { name: 'Edit page scope' }));

        expect(posted(screen.getByRole('dialog', { name: 'Page scope' }))).toEqual([{ kind: 'product', negate: true, params: { products: ['T_SHIRT'] } }]);
    });
});
