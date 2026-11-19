/**
 * Sources fill list fields, such as Products, when content is shown, instead of items picked by hand. A list a source
 * fills stores {"$source": code, "$params": {…}, "$limit": n}. See "List fields" in docs/spec/sources.md and "Field
 * values" in docs/spec/content-model.md
 */
import type { FieldDefinition, FieldSchema, JsonObject, JsonValue } from '../types';
import { isJsonObject } from '../values';

/** A source editors may pick, as the registry gives it. A disabled one isn't given. */
export interface SourceDefinition {
    code: string;
    label: string;
    /** The context keys it needs, such as product. */
    needs: string[];
    /**
     * The content types whose entries it fits as the entry, such as the category content types for In this category. Null
     * or absent when it fits any.
     */
    entryContentTypes?: string[] | null;
    /** Its parameters, shared by all translations, in display order. */
    fields: FieldDefinition[];
}

/** What a list field stores when a source fills it. A type, not an interface, so it is a JsonValue too. */
export type SourceValue = {
    $source: string;
    $params: JsonObject;
    $limit: number;
};

/** The source a list field's value holds, or null when it's hand-picked or empty. */
export function sourceOf(value: JsonValue | undefined): SourceValue | null {
    if (!isJsonObject(value) || typeof value.$source !== 'string') {
        return null;
    }

    return {
        $source: value.$source,
        $params: isJsonObject(value.$params) ? value.$params : {},
        $limit: typeof value.$limit === 'number' ? value.$limit : 1,
    };
}

/** The codes of the sources the field offers, in the order the "Filled by" select offers them, after Hand-picked. */
export function offeredSources(schema: FieldSchema): string[] {
    const options = schema['x-cms-options'];
    const sources = isJsonObject(options) ? options.sources : undefined;

    return Array.isArray(sources) ? sources.filter((code): code is string => typeof code === 'string') : [];
}

/** How many items a list starts to show when a source fills it: the field's limit. */
export function limitOf(schema: FieldSchema): number {
    const options = schema['x-cms-options'];
    const limit = isJsonObject(options) ? options.limit : undefined;

    return typeof limit === 'number' && limit >= 1 ? limit : 8;
}

/** A list filled by the source, with no parameters yet, showing as many items as the limit. */
export function filledBy(code: string, limit: number): SourceValue {
    return { $source: code, $params: {}, $limit: limit };
}

/**
 * The list with a parameter set, or removed when null, '' or []. A parameter that lists the categories of the collection
 * the changed one holds, such as From a collection's categories, is cleared too, as those categories are another
 * collection's.
 */
export function withSourceParam(source: SourceValue, name: string, value: JsonValue | null, fields: readonly FieldDefinition[] = []): SourceValue {
    const dependents = fields.filter(({ schema }) => categoriesOf(schema) === name).map((field) => field.name);
    const others = Object.fromEntries(Object.entries(source.$params).filter(([param]) => param !== name && (!dependents.includes(param) || source.$params[name] === value)));
    const removed = value === null || value === '' || (Array.isArray(value) && value.length === 0);

    return { ...source, $params: removed ? others : { ...others, [name]: value } };
}

/**
 * Whether the source fits the edited entry's content type as the entry: a source that fits only some, such as In this
 * category, is offered only in entries of them. A snippet's entry (null) can be of any content type, and outside the
 * builder (undefined) there is no entry.
 */
export function fitsEntry(source: SourceDefinition, entryContentType: string | null | undefined): boolean {
    return entryContentType === null || entryContentType === undefined || source.entryContentTypes === null || source.entryContentTypes === undefined || source.entryContentTypes.includes(entryContentType);
}

/** The parameter whose collection's categories an Entries field lists, such as contentType, or null when it lists no collection's. */
export function categoriesOf(schema: FieldSchema): string | null {
    const options = schema['x-cms-options'];
    const named = isJsonObject(options) ? options.categoriesOf : undefined;

    return typeof named === 'string' ? named : null;
}

/**
 * A parameter's schema as it shows beside the others: one that lists the categories of the collection another parameter
 * holds searches that collection's category content type, and is left out, null, until a collection with categories is
 * chosen. Any other shows as it is.
 *
 * @param categoryTypes each content type's category content type, by code
 */
export function paramSchema(schema: FieldSchema, params: JsonObject, categoryTypes: Readonly<Record<string, string | null>>): FieldSchema | null {
    const named = categoriesOf(schema);
    if (named === null) {
        return schema;
    }

    const collection = params[named];
    const categoryType = typeof collection === 'string' ? categoryTypes[collection] : undefined;
    if (typeof categoryType !== 'string') {
        return null;
    }

    return { ...schema, 'x-cms-options': { ...(schema['x-cms-options'] ?? {}), contentType: categoryType } };
}

/** The list showing another number of items. */
export function withLimit(source: SourceValue, limit: number): SourceValue {
    return { ...source, $limit: limit };
}
