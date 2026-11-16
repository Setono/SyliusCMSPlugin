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

/** The entry states of docs/spec/publishing.md#entry-states that the builder shows so far. */
export type EntryStatus = 'never_published' | 'published' | 'unpublished_changes';

export type AudienceMode = 'everyone' | 'only' | 'except';

/**
 * The entry's on-save settings, which take effect as soon as they're saved, outside the draft. Customer group values are
 * customer group codes, '@guest' and '@no_group'.
 */
export interface EntrySettings {
    enabled: boolean;
    /** UTC, such as 2026-11-27T00:00:00Z, or null. Inclusive. */
    showFrom: string | null;
    /** UTC, or null. Exclusive. */
    showUntil: string | null;
    /** Channel codes. */
    channels: string[];
    audience: { mode: AudienceMode; groups: string[] };
}

/** Someone else who has the entry open in the builder. */
export interface Editor {
    name: string;
}

/**
 * Something publish refuses, as entry_validate and a refused publish list it: in a block's field, in a block as a
 * whole, in an entry field, or in the entry as a whole.
 */
export interface ValidationError {
    /** The key of the block it's in, or null when it's in the entry. */
    block: string | null;
    /** The field it's in, or null when it's about the block or the entry as a whole. */
    field: string | null;
    /** The locale of a translatable value, or null. */
    locale: string | null;
    /** Translated, ready to show. */
    message: string;
}

export interface EntryResponse {
    entry: Entry;
    /** The working copy's version, which a save made on it carries. */
    lockToken: number;
    settings: EntrySettings;
    status: EntryStatus;
    /** Who else has the entry open. */
    editors: Editor[];
}

/**
 * A real clash between the editor's save and what a colleague saved since: the same field in the same locale, an edit
 * to a block the colleague deleted, or the deletion of a block the colleague changed something in. The server applied
 * everything else, but not this.
 */
export type Clash =
    | {
          kind: 'value';
          key: string;
          field: string;
          /** null for a shared field. */
          locale: string | null;
          mine: JsonValue | null;
          theirs: JsonValue | null;
      }
    | {
          /** An entry field, such as a translation's title. */
          kind: 'entry_value';
          field: string;
          /** null for a shared field. */
          locale: string | null;
          mine: JsonValue | null;
          theirs: JsonValue | null;
      }
    | { kind: 'deleted_block'; key: string }
    | { kind: 'changed_block'; key: string };

export interface SaveResponse {
    status: EntryStatus;
    /** The working copy's version after the save, which the next save carries. */
    lockToken: number;
    /** The working copy with colleagues' changes in it, when they saved since the last load. */
    entry?: Entry;
    clashes?: Clash[];
}

export interface PresenceResponse {
    /** Who else has the entry open. */
    editors: Editor[];
    /** The working copy's version, which is ahead of the builder's when colleagues saved since. */
    lockToken: number;
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

/** A named look editors pick for a block. The block stores its code in its style. */
export interface BlockStyleDefinition {
    code: string;
    label: string;
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
    /** The block styles it offers, those it declares first and then those added in config. */
    styles: BlockStyleDefinition[];
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
