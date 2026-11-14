/**
 * The shapes the builder exchanges with the server: the entry format and the stored block tree format
 * (docs/spec/content-model.md), and the registry of block types and field schemas.
 */

export type JsonValue = string | number | boolean | null | JsonValue[] | JsonObject;

export interface JsonObject {
    [key: string]: JsonValue;
}

/**
 * Field values keyed by field name. A shared field stores one value, a translatable field a map from
 * locale code to value. A field with no value is left out.
 */
export type FieldValues = Record<string, JsonValue>;

export interface TranslationSource {
    from: string;
    fingerprint: string;
}

/** Field name, then locale, then where that value was translated from. */
export type TranslationState = Record<string, Record<string, TranslationSource>>;

export interface VisibilityCondition {
    kind: string;
    negate: boolean;
    params: JsonObject;
}

export interface StoredBlock {
    key: string;
    type: string;
    version: number;
    style: string | null;
    fields: FieldValues;
    translated: TranslationState;
    conditions: VisibilityCondition[];
    children: StoredBlock[];
}

export interface StoredLayout {
    code: string;
    version: number;
    fields: FieldValues;
    translated: TranslationState;
}

export interface StoredBlockTree {
    layout: StoredLayout | null;
    blocks: StoredBlock[];
}

export interface EntryTranslation {
    shown: boolean;
    fields: FieldValues;
    translated: TranslationState;
}

export interface Entry {
    uuid: string;
    contentType: string;
    version: number;
    createdAt: string;
    fields: FieldValues;
    translations: Record<string, EntryTranslation>;
    tree: StoredBlockTree;
}

export type EntryStatus = 'never_published' | 'published';

export interface EntryResponse {
    entry: Entry;
    status: EntryStatus;
}

/** JSON Schema for one value (one locale's value, for a translatable field), plus the x-cms-* keywords. */
export interface FieldSchema {
    'x-cms-type': string;
    'x-cms-label': string;
    'x-cms-help': string | null;
    'x-cms-required': boolean;
    'x-cms-translatable': boolean;
    'x-cms-options'?: JsonObject;
    default?: JsonValue;
    [keyword: string]: JsonValue | undefined;
}

export interface FieldDefinition {
    name: string;
    schema: FieldSchema;
}

export interface BlockTypeDefinition {
    code: string;
    label: string;
    version: number;
    /** In display order. */
    fields: FieldDefinition[];
}

export interface Registry {
    blockTypes: BlockTypeDefinition[];
    /** The UI strings, already translated, keyed by translation key. */
    strings: Record<string, string>;
}
