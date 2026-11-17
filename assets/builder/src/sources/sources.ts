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

/** The list with a parameter set, or removed when null, '' or []. */
export function withSourceParam(source: SourceValue, name: string, value: JsonValue | null): SourceValue {
    const { [name]: _removed, ...others } = source.$params;
    const removed = value === null || value === '' || (Array.isArray(value) && value.length === 0);

    return { ...source, $params: removed ? others : { ...others, [name]: value } };
}

/** The list showing another number of items. */
export function withLimit(source: SourceValue, limit: number): SourceValue {
    return { ...source, $limit: limit };
}
