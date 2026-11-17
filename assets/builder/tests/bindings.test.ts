import { describe, expect, it } from 'vitest';
import { acceptedTypes, bindsWholeValue, fallbackIn, isBinding, offeredProperties, translatedValues, withFallback, withParam, type BindablePropertyDefinition } from '../src/bindings';
import type { FieldSchema } from '../src/types';
import { readFieldValue, writeFieldValue } from '../src/values';

const properties: BindablePropertyDefinition[] = [
    { binding: 'product.name', label: 'Product name', type: 'text', needs: ['product'], fields: [] },
    { binding: 'product.url', label: 'Product URL', type: 'url', needs: ['product'], fields: [] },
    { binding: 'taxon.name', label: 'Taxon name', type: 'text', needs: ['taxon'], fields: [] },
    { binding: 'app.stock', label: 'Stock', type: 'number', needs: ['product'], fields: [] },
    { binding: 'customer.first_name', label: 'Customer first name', type: 'text', needs: [], fields: [] },
];

function schema(type: string, bindable?: string[]): FieldSchema {
    return {
        'x-cms-type': type,
        'x-cms-label': 'Field',
        'x-cms-help': null,
        'x-cms-required': false,
        'x-cms-translatable': true,
        ...(bindable === undefined ? {} : { 'x-cms-bindable': bindable }),
    };
}

describe('what a field can be bound to', () => {
    it('should offer the properties whose type fits, of the context provided, and the channel and the customer everywhere', () => {
        expect(offeredProperties(properties, ['text', 'number'], ['product']).map(({ binding }) => binding)).toEqual(['product.name', 'app.stock', 'customer.first_name']);
        expect(offeredProperties(properties, ['text', 'number'], []).map(({ binding }) => binding)).toEqual(['customer.first_name']);
        expect(offeredProperties(properties, ['url'], ['product', 'taxon']).map(({ binding }) => binding)).toEqual(['product.url']);
    });

    it('should bind Text, Textarea, Number and a project field type whole, but not a Link or rich text, which hold their bindings inside', () => {
        expect(bindsWholeValue(schema('text', ['text', 'number']))).toBe(true);
        expect(bindsWholeValue(schema('app_rating', ['number']))).toBe(true);
        expect(bindsWholeValue(schema('link', ['url']))).toBe(false);
        expect(bindsWholeValue(schema('rich_text', ['text', 'number']))).toBe(false);
        expect(bindsWholeValue(schema('toggle'))).toBe(false);
        expect(acceptedTypes(schema('text', ['text', 'number', 'image']))).toEqual(['text', 'number']);
    });
});

describe('a bound value', () => {
    const binding = { $bind: 'product.name', $params: {}, $fallback: { en_US: 'This product' } };

    it('should be told from a locale map', () => {
        expect(isBinding(binding)).toBe(true);
        expect(isBinding({ en_US: 'Hello' })).toBe(false);
        expect(isBinding('product.name')).toBe(false);
    });

    it('should keep its fallback per locale for a translatable field, and one fallback for a shared one', () => {
        expect(fallbackIn(binding, true, 'en_US')).toBe('This product');
        expect(fallbackIn(binding, true, 'da_DK')).toBeUndefined();
        expect(withFallback(binding, true, 'da_DK', 'Dette produkt').$fallback).toEqual({ en_US: 'This product', da_DK: 'Dette produkt' });
        expect(withFallback(binding, true, 'en_US', '').$fallback).toBeNull();
        expect(withFallback({ ...binding, $fallback: null }, false, 'en_US', 3).$fallback).toBe(3);
    });

    it('should set and remove its parameters', () => {
        const attribute = withParam({ $bind: 'product.attribute', $params: {}, $fallback: null }, 'attribute', 'material');

        expect(attribute.$params).toEqual({ attribute: 'material' });
        expect(withParam(attribute, 'attribute', null).$params).toEqual({});
    });

    it("should have no value in a locale, and give its fallback as what's translated", () => {
        expect(readFieldValue({ text: binding }, 'text', true, 'en_US')).toBeUndefined();
        expect(translatedValues(binding)).toEqual({ en_US: 'This product' });
        expect(translatedValues({ en_US: 'Hello' })).toEqual({ en_US: 'Hello' });
    });

    it("should be replaced by a locale's value typed in its place", () => {
        const fields = { text: binding };

        writeFieldValue(fields, 'text', true, 'da_DK', 'Hej');

        expect(fields).toEqual({ text: { da_DK: 'Hej' } });
    });
});
