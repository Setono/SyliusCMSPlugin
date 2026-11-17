import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { BindablePropertyDefinition } from '../src/bindings';
import { BindingsProvider } from '../src/bindingsContext';
import { FieldRow } from '../src/form/FieldRow';
import { LinkControl } from '../src/form/LinkControl';
import { RichTextControl } from '../src/form/RichTextControl';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import { LinkTargetsProvider } from '../src/linkTargets';
import type { FieldSchema, JsonValue } from '../src/types';
import { heading, strings } from './fixtures';

const [headingText] = heading.fields.map((field) => field.schema) as [FieldSchema];
const textSchema: FieldSchema = { ...headingText, 'x-cms-bindable': ['text', 'number'] };

const properties: BindablePropertyDefinition[] = [
    { binding: 'product.name', label: 'Product name', type: 'text', needs: ['product'], fields: [] },
    {
        binding: 'product.attribute',
        label: 'Product attribute',
        type: 'text',
        needs: ['product'],
        fields: [
            {
                name: 'attribute',
                schema: {
                    type: 'string',
                    enum: ['material', 'fit'],
                    'x-cms-type': 'choice',
                    'x-cms-label': 'Attribute',
                    'x-cms-help': null,
                    'x-cms-required': true,
                    'x-cms-translatable': false,
                    'x-cms-options': { choices: { material: 'Material', fit: 'Fit' }, multiple: false },
                },
            },
        ],
    },
    { binding: 'product.url', label: 'Product URL', type: 'url', needs: ['product'], fields: [] },
    { binding: 'taxon.name', label: 'Taxon name', type: 'text', needs: ['taxon'], fields: [] },
    { binding: 'customer.first_name', label: 'Customer first name', type: 'text', needs: [], fields: [] },
];

afterEach(cleanup);

function renderRow(stored: JsonValue | undefined, set = vi.fn(), { schema = textSchema, provided = ['product'] }: { schema?: FieldSchema; provided?: string[] } = {}) {
    render(
        <TranslatorProvider value={createTranslator(strings)}>
            <BindingsProvider value={{ properties, provided }}>
                <FieldRow id="field" schema={schema} value={undefined} onChange={vi.fn()} locale="en_US" binding={{ stored, set }} />
            </BindingsProvider>
        </TranslatorProvider>,
    );

    return set;
}

describe('binding a field', () => {
    it('should list the properties whose type fits and whose context is provided, plus the customer, and bind the field, keeping what it holds as the fallback', async () => {
        const set = renderRow({ en_US: 'This product' });

        fireEvent.click(screen.getByRole('button', { name: 'Bind Text to a property' }));
        const list = await screen.findByRole('dialog', { name: 'Bind Text to a property' });
        expect(within(list).getAllByRole('button').map((button) => button.textContent)).toEqual(['Product name', 'Product attribute', 'Customer first name']);

        fireEvent.click(within(list).getByRole('button', { name: 'Product name' }));

        expect(set).toHaveBeenCalledWith({ $bind: 'product.name', $params: {}, $fallback: { en_US: 'This product' } });
    });

    it("should leave out a field's ⚡ where nothing fits", () => {
        renderRow(undefined, vi.fn(), { schema: { ...textSchema, 'x-cms-type': 'number', 'x-cms-bindable': ['number'] } });

        expect(screen.queryByRole('button', { name: 'Bind Text to a property' })).toBeNull();
    });

    it("should show a bound field's property and its fallback in the edited locale in place of its control", () => {
        const set = renderRow({ $bind: 'product.name', $params: {}, $fallback: { en_US: 'This product', da_DK: 'Dette produkt' } });

        const bound = screen.getByRole('group', { name: 'Shows Product name' });
        expect(within(bound).getByText('Product name')).toBeInTheDocument();
        expect(screen.queryByRole('textbox', { name: 'Text' })).toBeNull();

        const fallback = screen.getByRole('textbox', { name: 'Fallback' });
        expect(fallback).toHaveValue('This product');

        fireEvent.change(fallback, { target: { value: 'It' } });
        expect(set).toHaveBeenLastCalledWith({ $bind: 'product.name', $params: {}, $fallback: { en_US: 'It', da_DK: 'Dette produkt' } });
    });

    it('should unbind a field back to its fallback', () => {
        const set = renderRow({ $bind: 'product.name', $params: [], $fallback: { en_US: 'This product' } });

        fireEvent.click(screen.getByRole('button', { name: 'Stop showing Product name' }));

        expect(set).toHaveBeenCalledWith({ en_US: 'This product' });
    });

    it("should set a bound property's parameters", () => {
        const set = renderRow({ $bind: 'product.attribute', $params: {}, $fallback: null });

        fireEvent.change(screen.getByRole('combobox', { name: 'Attribute' }), { target: { value: 'fit' } });

        expect(set).toHaveBeenCalledWith({ $bind: 'product.attribute', $params: { attribute: 'fit' }, $fallback: null });
    });

    it('should say a bound property the code no longer has shows its fallback', () => {
        renderRow({ $bind: 'product.colour', $params: {}, $fallback: null });

        expect(screen.getByRole('group', { name: 'Shows product.colour' })).toBeInTheDocument();
        expect(screen.getByText('This property no longer exists, so the fallback shows.')).toBeInTheDocument();
    });
});

describe('binding a link to a URL', () => {
    const linkSchema: FieldSchema = {
        type: 'object',
        'x-cms-type': 'link',
        'x-cms-label': 'Link',
        'x-cms-help': null,
        'x-cms-required': true,
        'x-cms-translatable': false,
        'x-cms-bindable': ['url'],
        'x-cms-options': { targets: ['product', 'url'], translatable: null },
    };

    function renderLink(value: JsonValue, onChange = vi.fn()) {
        render(
            <TranslatorProvider value={createTranslator(strings)}>
                <LinkTargetsProvider
                    value={[
                        { code: 'product', label: 'Product', translatableRef: false },
                        { code: 'url', label: 'URL', translatableRef: true },
                    ]}
                >
                    <BindingsProvider value={{ properties, provided: ['product'] }}>
                        <LinkControl id="link" schema={linkSchema} value={value} onChange={onChange} locale="en_US" />
                    </BindingsProvider>
                </LinkTargetsProvider>
            </TranslatorProvider>,
        );

        return onChange;
    }

    it("should offer the url properties for a link to a URL and bind its ref, keeping its label", async () => {
        const onChange = renderLink({ target: 'url', ref: {}, anchor: null, label: { en_US: 'View product' } });

        fireEvent.click(screen.getByRole('button', { name: 'Bind setono_sylius_cms.builder.link.url to a property' }));
        const list = await screen.findByRole('dialog');
        expect(within(list).getAllByRole('button').map((button) => button.textContent)).toEqual(['Product URL']);
        fireEvent.click(within(list).getByRole('button', { name: 'Product URL' }));

        expect(onChange).toHaveBeenCalledWith({ target: 'url', ref: { $bind: 'product.url', $params: {}, $fallback: null }, anchor: null, label: { en_US: 'View product' } });
    });

    it("should show a bound ref's property, and unbind it", () => {
        const onChange = renderLink({ target: 'url', ref: { $bind: 'product.url', $params: {}, $fallback: null }, anchor: null, label: { en_US: 'View product' } });

        expect(screen.getByRole('group', { name: 'Shows Product URL' })).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Stop showing Product URL' }));

        expect(onChange).toHaveBeenCalledWith({ target: 'url', ref: {}, anchor: null, label: { en_US: 'View product' } });
    });

    it('should offer no binding for a link to a product', () => {
        renderLink({ target: 'product', ref: '', anchor: null, label: {} });

        expect(screen.queryByRole('button', { name: /to a property/ })).toBeNull();
    });
});

describe('binding tokens in rich text', () => {
    const richTextSchema: FieldSchema = {
        type: 'object',
        'x-cms-type': 'rich_text',
        'x-cms-label': 'Content',
        'x-cms-help': null,
        'x-cms-required': false,
        'x-cms-translatable': true,
        'x-cms-bindable': ['text', 'number'],
    };

    it("should insert a token from the toolbar's Binding button, with its fallback, and show it by its property's label", async () => {
        const onChange = vi.fn();
        render(
            <TranslatorProvider value={createTranslator(strings)}>
                <BindingsProvider value={{ properties, provided: ['product'] }}>
                    <RichTextControl id="content" schema={richTextSchema} value={null} onChange={onChange} locale="en_US" />
                </BindingsProvider>
            </TranslatorProvider>,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Binding' }));
        const panel = screen.getByRole('group', { name: 'Binding' });
        expect(within(panel).getAllByRole('option').map((option) => option.textContent)).toEqual(['Product name', 'Product attribute', 'Customer first name']);
        fireEvent.change(within(panel).getByRole('textbox', { name: 'Fallback' }), { target: { value: 'this product' } });
        fireEvent.click(within(panel).getByRole('button', { name: 'Insert' }));

        await waitFor(() =>
            expect(onChange).toHaveBeenLastCalledWith({
                type: 'doc',
                content: [{ type: 'paragraph', content: [{ type: 'cmsBinding', attrs: { property: 'product.name', params: {}, fallback: 'this product' } }] }],
            }),
        );
        expect(screen.getByRole('textbox', { name: 'Content' }).textContent).toContain('⚡ Product name');
    });

    it('should leave out the Binding button where no property fits', () => {
        render(
            <TranslatorProvider value={createTranslator(strings)}>
                <BindingsProvider value={{ properties: [], provided: [] }}>
                    <RichTextControl id="content" schema={richTextSchema} value={null} onChange={vi.fn()} locale="en_US" />
                </BindingsProvider>
            </TranslatorProvider>,
        );

        expect(screen.queryByRole('button', { name: 'Binding' })).toBeNull();
    });
});
