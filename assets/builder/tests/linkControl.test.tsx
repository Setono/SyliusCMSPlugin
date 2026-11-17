import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { LinkTargetDefinition } from '../src/links';
import { LinkTargetsProvider } from '../src/linkTargets';
import type { SearchApi, SearchResult } from '../src/search/api';
import { SearchProvider } from '../src/search/context';
import type { FieldSchema, JsonValue } from '../src/types';

const strings = {
    'setono_sylius_cms.builder.all_languages': 'All languages',
    'setono_sylius_cms.builder.link.anchor': 'Anchor',
    'setono_sylius_cms.builder.link.change': 'Change…',
    'setono_sylius_cms.builder.link.choose': 'Choose…',
    'setono_sylius_cms.builder.link.label': 'Label',
    'setono_sylius_cms.builder.link.label_help': 'In this language. Leave it empty to show the name of what it links to.',
    'setono_sylius_cms.builder.link.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.link.search': 'Search: %target%',
    'setono_sylius_cms.builder.link.url': 'URL',
    'setono_sylius_cms.builder.search.searching': 'Searching…',
    'setono_sylius_cms.builder.search.no_results': 'Nothing found',
    'setono_sylius_cms.builder.search.failed': 'The search failed. Try again.',
};

const linkTargets: LinkTargetDefinition[] = [
    { code: 'entry', label: 'Entry', translatableRef: false },
    { code: 'product', label: 'Product', translatableRef: false },
    { code: 'url', label: 'URL', translatableRef: true },
];

const linkSchema: FieldSchema = {
    type: 'object',
    'x-cms-type': 'link',
    'x-cms-label': 'Link',
    'x-cms-help': null,
    'x-cms-required': true,
    'x-cms-translatable': false,
    'x-cms-options': { targets: ['entry', 'product', 'url'], translatable: null },
};

const products: SearchResult[] = [
    { ref: 'T_SHIRT_RED', name: 'Red T-shirt', thumbnail: null },
    { ref: 'T_SHIRT_BLUE', name: 'Blue T-shirt', thumbnail: '/media/cache/blue.jpg' },
    { ref: 'MUG', name: 'Mug', thumbnail: null },
];

/** The store's products, searched by name and looked up by code. */
function fakeSearch(): SearchApi & { search: ReturnType<typeof vi.fn>; find: ReturnType<typeof vi.fn> } {
    return {
        search: vi.fn(async (kind: string, query: string) => (kind === 'product' ? products.filter(({ name }) => name.toLowerCase().includes(query.toLowerCase())) : [])),
        find: vi.fn(async (kind: string, refs: readonly string[]) => (kind === 'product' ? products.filter(({ ref }) => refs.includes(ref)) : [])),
    };
}

/** A field whose value lives in the test, as the builder's store keeps it, with every change recorded. */
function Field({ schema, initial, locale, onChange }: { schema: FieldSchema; initial?: JsonValue; locale: string; onChange: (value: JsonValue | null) => void }) {
    const [value, setValue] = useState<JsonValue | undefined>(initial);

    return (
        <FieldRow
            id="field"
            schema={schema}
            value={value}
            locale={locale}
            onChange={(next) => {
                onChange(next);
                setValue(next ?? undefined);
            }}
        />
    );
}

function renderField({ initial, schema = linkSchema, locale = 'en_US', searched = true }: { initial?: JsonValue; schema?: FieldSchema; locale?: string; searched?: boolean } = {}) {
    const onChange = vi.fn();
    const search = fakeSearch();
    const field = (
        <LinkTargetsProvider value={linkTargets}>
            <Field schema={schema} initial={initial} locale={locale} onChange={onChange} />
        </LinkTargetsProvider>
    );
    render(<TranslatorProvider value={createTranslator(strings)}>{searched ? <SearchProvider api={search}>{field}</SearchProvider> : field}</TranslatorProvider>);

    return { onChange, search };
}

describe('a link field', () => {
    it('should offer the targets the field links to under its label, the first one picked, with nothing chosen', () => {
        renderField();

        const target = screen.getByRole('combobox', { name: 'Link' });
        expect(target).toHaveValue('entry');
        expect(within(target).getAllByRole('option').map((option) => option.textContent)).toEqual(['Entry', 'Product', 'URL']);
        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
    });

    it('should search what the target links to and store the ref of what is picked, leaving the label empty', async () => {
        const { onChange, search } = renderField();

        fireEvent.change(screen.getByRole('combobox', { name: 'Link' }), { target: { value: 'product' } });
        expect(onChange).toHaveBeenLastCalledWith({ target: 'product', ref: '', anchor: null, label: {} });

        fireEvent.click(screen.getByRole('button', { name: 'Choose…' }));
        const box = await screen.findByRole('combobox', { name: 'Search: Product' });
        await screen.findByRole('option', { name: 'Mug' });
        expect(search.search).toHaveBeenCalledWith('product', '');

        fireEvent.change(box, { target: { value: 'shirt' } });
        const red = await screen.findByRole('option', { name: 'Red T-shirt' });
        expect(search.search).toHaveBeenLastCalledWith('product', 'shirt');
        expect(screen.queryByRole('option', { name: 'Mug' })).not.toBeInTheDocument();
        fireEvent.click(red);

        expect(onChange).toHaveBeenLastCalledWith({ target: 'product', ref: 'T_SHIRT_RED', anchor: null, label: {} });
        await waitFor(() => expect(screen.queryByRole('combobox', { name: 'Search: Product' })).not.toBeInTheDocument());
        expect(screen.getByText('Red T-shirt')).toBeInTheDocument();
        expect(screen.getByRole('textbox', { name: 'Label' })).toHaveAttribute('placeholder', 'Red T-shirt');
    });

    it('should pick what the arrow keys reach with Enter', async () => {
        const { onChange } = renderField({ initial: { target: 'product', ref: '', anchor: null, label: {} } });

        fireEvent.click(screen.getByRole('button', { name: 'Choose…' }));
        const box = await screen.findByRole('combobox', { name: 'Search: Product' });
        await screen.findByRole('option', { name: 'Mug' });

        fireEvent.keyDown(box, { key: 'ArrowDown' });
        expect(screen.getByRole('option', { name: 'Blue T-shirt' })).toHaveAttribute('aria-selected', 'true');
        fireEvent.keyDown(box, { key: 'Enter' });

        expect(onChange).toHaveBeenLastCalledWith({ target: 'product', ref: 'T_SHIRT_BLUE', anchor: null, label: {} });
    });

    it('should name what a link holds by looking it up', async () => {
        const { search } = renderField({ initial: { target: 'product', ref: 'MUG', anchor: null, label: {} } });

        expect(await screen.findByText('Mug')).toBeInTheDocument();
        expect(search.find).toHaveBeenCalledWith('product', ['MUG']);
        expect(screen.getByRole('button', { name: 'Change…' })).toBeEnabled();
    });

    it('should show the ref of what a link holds when it can not be looked up', async () => {
        renderField({ initial: { target: 'entry', ref: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', anchor: null, label: {} } });

        expect(await screen.findByText('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e')).toBeInTheDocument();
    });

    it('should keep the label of the edited locale, and show what it falls back to', () => {
        const { onChange } = renderField({ initial: { target: 'product', ref: 'MUG', anchor: null, label: { en_US: 'Buy a mug' } }, locale: 'de_DE' });

        const label = screen.getByRole('textbox', { name: 'Label' });
        expect(label).toHaveValue('');
        expect(label).toHaveAccessibleDescription('In this language. Leave it empty to show the name of what it links to.');

        fireEvent.change(label, { target: { value: 'Becher kaufen' } });

        expect(onChange).toHaveBeenLastCalledWith({ target: 'product', ref: 'MUG', anchor: null, label: { en_US: 'Buy a mug', de_DE: 'Becher kaufen' } });
    });

    it('should type a URL in each locale', () => {
        const { onChange } = renderField({ initial: { target: 'url', ref: { en_US: 'https://example.com/en' }, anchor: null, label: {} } });

        expect(screen.queryByRole('button', { name: 'Choose…' })).not.toBeInTheDocument();
        const url = screen.getByRole('textbox', { name: 'URL' });
        expect(url).toHaveValue('https://example.com/en');

        fireEvent.change(url, { target: { value: 'mailto:shop@example.com' } });

        expect(onChange).toHaveBeenLastCalledWith({ target: 'url', ref: { en_US: 'mailto:shop@example.com' }, anchor: null, label: {} });
    });

    it('should keep the anchor without its hash', () => {
        const { onChange } = renderField({ initial: { target: 'product', ref: 'MUG', anchor: null, label: {} } });

        fireEvent.change(screen.getByRole('textbox', { name: 'Anchor' }), { target: { value: '#reviews' } });

        expect(onChange).toHaveBeenLastCalledWith({ target: 'product', ref: 'MUG', anchor: 'reviews', label: {} });
    });

    it('should keep one label for every locale when the field keeps one link for every locale', () => {
        const { onChange } = renderField({ initial: { target: 'url', ref: 'tel:+4512345678', anchor: null, label: null }, schema: { ...linkSchema, 'x-cms-options': { targets: null, translatable: false } }, locale: 'de_DE' });

        expect(screen.getByRole('textbox', { name: 'URL' })).toHaveValue('tel:+4512345678');
        fireEvent.change(screen.getByRole('textbox', { name: 'Label' }), { target: { value: 'Ring an' } });

        expect(onChange).toHaveBeenLastCalledWith({ target: 'url', ref: 'tel:+4512345678', anchor: null, label: 'Ring an' });
    });

    it('should carry no All languages badge while it keeps its label and URL per locale', () => {
        renderField({ initial: { target: 'product', ref: 'MUG', anchor: null, label: { en_US: 'Mugs' } } });
        expect(screen.queryByText('All languages')).not.toBeInTheDocument();
    });

    it('should carry the All languages badge when it keeps one link for every locale', () => {
        renderField({ initial: { target: 'product', ref: 'MUG', anchor: null, label: null }, schema: { ...linkSchema, 'x-cms-options': { targets: null, translatable: false } } });
        expect(screen.getByText('All languages')).toBeInTheDocument();
    });

    it('should not let anything be chosen without a search', () => {
        renderField({ initial: { target: 'product', ref: '', anchor: null, label: {} }, searched: false });

        expect(screen.getByRole('button', { name: 'Choose…' })).toBeDisabled();
    });
});
