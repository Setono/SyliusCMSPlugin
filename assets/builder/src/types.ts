/**
 * The shapes the builder exchanges with the server: the entry format and the stored block tree format
 * (docs/spec/content-model.md), and the registry of block types and field schemas.
 */
import type { LinkTargetDefinition } from './links';
import type { MediaLimits } from './media/config';
import type { BindablePropertyDefinition } from './bindings';
import type { SourceDefinition } from './sources/sources';

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

/** A visibility condition in the stored condition format. A type, not an interface, so it is a JsonValue too. */
export type VisibilityCondition = {
    kind: string;
    negate: boolean;
    params: JsonObject;
};

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
    /** Field name, then where the translation's value was translated from. */
    translated: Record<string, TranslationSource>;
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

/** The state of the entry's draft, of the entry states of docs/spec/publishing.md#entry-states. */
export type EntryStatus = 'never_published' | 'published' | 'unpublished_changes';

/**
 * The state of the entry's scheduled publish, of the entry states of docs/spec/publishing.md#entry-states: it waits,
 * which locks the working copy, it's overdue, which locks it too, or it was stopped when it ran.
 */
export type ScheduleStatus = 'publishes_at_a_time' | 'scheduled_publish_overdue' | 'scheduled_publish_failed';

/** The entry's scheduled publish. */
export interface Schedule {
    /** When it publishes, in UTC, such as 2026-11-27T08:00:00Z. */
    at: string;
    /** Who scheduled it, or null when no one is known. */
    by: string | null;
    status: ScheduleStatus;
    /** Why it was stopped when it ran, or null. */
    reason: string | null;
}

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
    /** "Homepage of": the codes of the channels a page is the homepage of. Empty for every content type but Page. */
    homepageOf: string[];
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
    /** The entry's scheduled publish, or null when it has none. */
    schedule?: Schedule | null;
    /** Who else has the entry open. */
    editors: Editor[];
    /**
     * The untranslated and outdated counts of each locale the entry has a translation in, as the admin shows them. The
     * builder counts them itself as the working copy changes.
     */
    completeness?: Record<string, { untranslated: number; outdated: number }>;
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
    /**
     * What the server didn't save, with why, each keeping its last saved value on the server: the slugs another route
     * takes the URL of, by locale, and a snippet's declared context while something is in the way, in the field context.
     * A refused context comes with the working copy in entry, which takes the change back.
     */
    refused?: ValidationError[];
}

/** What the URL & SEO tab shows beside the slug, as the server has it. */
export interface EntryUrlsResponse {
    /** URLs other entries have drafted, and published URLs another route takes now, pointing at the slug in a locale. */
    warnings: ValidationError[];
    /** The paths of the URLs of the old slugs that redirect, by locale, newest first. */
    oldUrls: Record<string, string[]>;
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
    /** The tag name of the custom element a project's own field type is entered with. The shipped field types have none. */
    'x-cms-control'?: string;
    'x-cms-label': string;
    'x-cms-help': string | null;
    'x-cms-required': boolean;
    'x-cms-translatable': boolean;
    'x-cms-options'?: JsonObject;
    /** The binding types a field of its field type accepts: text, number or url. Absent when it can't be bound. */
    'x-cms-bindable'?: string[];
    /** Set when its field type holds its bindings inside its values, as a Link's ref and rich text's tokens do, rather than as the whole value. */
    'x-cms-binds-inside'?: boolean;
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
    /**
     * The context keys it needs, such as product: the picker offers it only where the entry provides them or the snippet
     * declares them. None without it.
     */
    needs?: string[];
    /** In display order. */
    fields: FieldDefinition[];
}

/** A frame around the entry's tree, with presentation fields of its own. See docs/spec/layouts-and-presets.md */
export interface LayoutDefinition {
    code: string;
    label: string;
    version: number;
    /** A public path to its wireframe card, or null. */
    thumbnail: string | null;
    /** The content type's default layout, which an entry in a layout that was removed or disabled renders in. */
    default: boolean;
    /** Switched off in the config: the picker leaves it out, and an entry in it renders in the default layout. */
    disabled: boolean;
    /** The entry fields its template prints, by name. The others are "Not shown in this layout". */
    shows: string[];
    /** In display order. */
    fields: FieldDefinition[];
}

export interface ContentTypeDefinition {
    code: string;
    label: string;
    /** What the top level of its tree may hold, or null when it has no block tree. */
    blocks: AllowedBlockType[] | null;
    /** Its URL prefix, '' for URLs at the root, or null when it has no URL of its own, so no slug. */
    prefix?: string | null;
    /** Whether its entries can be a channel's homepage, which only pages can. Without it, they can't. */
    homepage?: boolean;
    /**
     * The context keys its entries provide their own tree, or null for a snippet, which declares them in its context
     * field. An entry provides itself, as entry, without it.
     */
    provides?: string[] | null;
    /** Its layouts, the default first, or none for a content type without its own URL. */
    layouts: LayoutDefinition[];
}

/** A key of host-page context, such as product, named by the pages that provide it, such as "Product page". */
export interface ContextKeyDefinition {
    key: string;
    label: string;
}

/** A slot snippets are placed in, with the context keys it provides. */
export interface SlotDefinition {
    identifier: string;
    label: string;
    provides: string[];
}

/** A kind of visibility condition, whose parameters are fields shared by all translations. */
export interface ConditionKindDefinition {
    code: string;
    label: string;
    /** How a condition of the kind that matches is worded, such as "is one of" or a date window's "is inside". */
    matches: string;
    /** How a negated one is worded, such as "is not one of" or "is outside". */
    matchesNot: string;
    /** The context keys it needs: it's offered only where the entry provides them or the snippet declares them. None without it. */
    needs?: string[];
    /** In display order. */
    fields: FieldDefinition[];
}

export interface Registry {
    blockTypes: BlockTypeDefinition[];
    contentTypes: ContentTypeDefinition[];
    /** In the order the condition picker lists them. */
    conditionKinds: ConditionKindDefinition[];
    /** What links lead to, in the order Link fields offer them. Without them, Link fields offer nothing. */
    linkTargets?: LinkTargetDefinition[];
    /** The sources editors may pick, in the order the "Filled by" select offers them. A disabled one isn't given. */
    sources?: SourceDefinition[];
    /** The context keys, in the order they're declared. */
    contextKeys?: ContextKeyDefinition[];
    /** The slots, in the order they're declared. */
    slots?: SlotDefinition[];
    /** The bindable properties, in the order the binding picker lists them. */
    bindableProperties?: BindablePropertyDefinition[];
    /** The effective upload limit and the MIME types of each kind of media item, which the media picker checks a file against. */
    media?: MediaLimits;
    /** The UI strings, already translated, keyed by translation key. */
    strings: Record<string, string>;
}
