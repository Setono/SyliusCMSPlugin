/**
 * Bindings in the builder: a field, a link's ref or a token in rich text that shows a property of the host-page
 * context, the channel or the customer instead of a fixed value. Pure functions, with no React. See "Bindings" in
 * docs/spec/slots.md and docs/spec/builder.md
 */
import type { FieldDefinition, FieldSchema, JsonObject, JsonValue } from './types';
import { isJsonObject, normalizeValue } from './values';

/** What a bindable property resolves to, which decides the fields it can be bound to. */
export type BindingType = 'text' | 'number' | 'url';

/** A bindable property, as the registry has it. */
export interface BindablePropertyDefinition {
    /** What a binding stores: "<context key>.<code>", such as product.name. */
    binding: string;
    label: string;
    type: BindingType;
    /** The context keys it needs the entry to provide or the snippet to declare: none for the channel's and the customer's. */
    needs: string[];
    /** Its parameters, shared by all translations, such as Product attribute's attribute. */
    fields: FieldDefinition[];
}

/**
 * What a bound field stores in place of its value: the property, its parameters, and what the field would otherwise store,
 * a value, a locale map of values for a translatable field, or null.
 */
export type StoredBinding = JsonObject & { $bind: string; $params: JsonObject; $fallback: JsonValue };

/** The binding types rich text's tokens accept. */
export const TOKEN_TYPES: readonly BindingType[] = ['text', 'number'];

/** Whether a stored value is a binding: a map with a "$bind" property. */
export function isBinding(value: JsonValue | undefined): value is StoredBinding {
    return isJsonObject(value) && typeof value.$bind === 'string';
}

/** A stored binding as the builder edits it, with its parameters as a map, however it was stored. */
export function bindingOf(value: StoredBinding): StoredBinding {
    return { $bind: value.$bind, $params: isJsonObject(value.$params) ? value.$params : {}, $fallback: value.$fallback ?? null };
}

/** A new binding of the property, with no parameters. */
export function newBinding(property: string, fallback: JsonValue = null): StoredBinding {
    return { $bind: property, $params: {}, $fallback: fallback };
}

/** The binding types a field accepts, none when it can't be bound. */
export function acceptedTypes(schema: FieldSchema): BindingType[] {
    return (schema['x-cms-bindable'] ?? []).filter((type): type is BindingType => type === 'text' || type === 'number' || type === 'url');
}

/**
 * Whether a field can show a binding as its whole value, as Text, Textarea and Number fields can. A field type whose
 * handler holds them inside its values, as Link and rich text do, says so in x-cms-binds-inside.
 */
export function bindsWholeValue(schema: FieldSchema): boolean {
    return acceptedTypes(schema).length > 0 && schema['x-cms-binds-inside'] !== true;
}

/**
 * The properties a field of these binding types can show where the entry provides these context keys or the snippet
 * declares them, in the registry's order. The channel's and the customer's fit everywhere.
 */
export function offeredProperties(properties: readonly BindablePropertyDefinition[], accepts: readonly string[], provided: readonly string[]): BindablePropertyDefinition[] {
    return properties.filter(({ type, needs }) => accepts.includes(type) && needs.every((key) => provided.includes(key)));
}

/** The property a binding shows, or undefined for one the code no longer has. */
export function propertyOf(properties: readonly BindablePropertyDefinition[], binding: string): BindablePropertyDefinition | undefined {
    return properties.find((property) => property.binding === binding);
}

/** The context key, channel or customer a binding reads, which the picker groups properties by. */
export function contextKeyOf(binding: string): string {
    return binding.split('.')[0] ?? binding;
}

/** A binding's fallback in the edited locale: its one value for a shared field, and the locale's for a translatable one. */
export function fallbackIn(binding: StoredBinding, translatable: boolean, locale: string): JsonValue | undefined {
    if (!translatable) {
        return binding.$fallback ?? undefined;
    }

    return isJsonObject(binding.$fallback) ? binding.$fallback[locale] : undefined;
}

/** The binding with its fallback set in the edited locale, or its one fallback for a shared field. An empty value removes it. */
export function withFallback(binding: StoredBinding, translatable: boolean, locale: string, value: JsonValue | null): StoredBinding {
    const normalized = normalizeValue(value ?? undefined);
    if (!translatable) {
        return { ...binding, $fallback: normalized };
    }

    const { [locale]: _, ...others } = isJsonObject(binding.$fallback) ? binding.$fallback : {};
    const fallback = normalized === null ? others : { ...others, [locale]: normalized };

    return { ...binding, $fallback: Object.keys(fallback).length === 0 ? null : fallback };
}

/** What a translatable field keeps per locale: its values by locale, or a binding's fallback. */
export function translatedValues(stored: JsonValue | undefined): JsonValue | undefined {
    return isBinding(stored) ? (stored.$fallback ?? undefined) : stored;
}

/** The binding with a parameter set. An empty value removes it. */
export function withParam(binding: StoredBinding, name: string, value: JsonValue | null): StoredBinding {
    const { [name]: _, ...others } = binding.$params;
    const normalized = normalizeValue(value ?? undefined);

    return { ...binding, $params: normalized === null ? others : { ...others, [name]: normalized } };
}
