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

/** A block type a spot in the tree may hold, with the fewest and the most blocks of it there. */
export interface AllowedBlockType {
    type: string;
    min: number | null;
    max: number | null;
}

export interface BlockTypeDefinition {
    code: string;
    label: string;
    version: number;
    /** A public path to the picker's preview image. */
    thumbnail: string | null;
    /** Switched off in the config: the picker leaves it out, but blocks of it already in the tree stay. */
    disabled: boolean;
    /** The only block types it may sit inside, or null for anywhere a parent allows it. */
    parents: string[] | null;
    /** What its children may be, with the rules already resolved: '*' and parents are taken into account. */
    children: AllowedBlockType[];
    /** In display order. */
    fields: FieldDefinition[];
}

export interface ContentTypeDefinition {
    code: string;
    label: string;
    /** What the top level of its tree may hold, or null when it has no block tree. */
    blocks: AllowedBlockType[] | null;
}

export interface Registry {
    blockTypes: BlockTypeDefinition[];
    contentTypes: ContentTypeDefinition[];
    /** The UI strings, already translated, keyed by translation key. */
    strings: Record<string, string>;
}
