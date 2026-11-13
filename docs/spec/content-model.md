# Content model

Entries, content types, the block tree, fields and references, and the JSON formats they're stored in.

- **Terms:** [content structure](../../GLOSSARY.md#content-structure) and [entries](../../GLOSSARY.md#entries).
- **Decisions:** [ADR 0001](../adr/0001-block-trees-as-json-and-entries-as-single-table-inheritance.md) (block trees as JSON, entries as single table inheritance), [ADR 0002](../adr/0002-references-by-code-and-uuid.md) (references by code and UUID), [ADR 0007](../adr/0007-translations-share-one-block-tree.md) (one shared tree), [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (drafts), [ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md) (the stored format is a contract), [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (typed properties are fields).

## Entries

Every entry has these, whatever its content type:

| What | Notes |
| --- | --- |
| UUID | Its identity everywhere: references, transfer, the future API. The plugin generates a UUID v7, and imports accept any valid UUID. |
| Content type | Its code is the single-table-inheritance discriminator. |
| Translations | One per locale, each with a **title** and a **shown** switch (held back when off). A content type with its own URL adds a slug, a meta title and a meta description. See [Localization](localization.md). |
| Block tree | One for all translations, as JSON on the entry. Present on every entry, and empty when the content type has no block tree. |
| Channels | A new entry starts in every channel. |
| `enabled` | On for a new entry. |
| Date window | Optional "Show from" and "Show until", as columns. |
| Audience | Everyone, only some customer groups, or everyone except some ([ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md)). See [Targeting](targeting.md#an-entrys-audience). |
| `changedAt` | When its published content or an on-save setting last changed. See [Transfer](transfer.md#stale-records). |

Content types add more:

- **With its own URL:** a slug per translation, a meta title and a meta description per translation, a shared "Hide from search engines" switch (`noindex`), and a layout. See [URLs and SEO](urls-and-seo.md).
- **Page:** "Homepage of", a list of channel codes. See [URLs and SEO](urls-and-seo.md#homepage).
- **Snippet:** the host-page context it needs, a list of context keys. See [Slots](slots.md#snippets-and-their-context).
- **Ordered by position**, such as FAQ items: a position that editors set by dragging in the admin list.
- **Fields of their own**, declared by the content type. See [Content types](#content-types).

### What's drafted and what takes effect on save

An entry's content goes through its [draft](../../GLOSSARY.md#publishing) and shows only once it's published ([Publishing](publishing.md)). That covers the content type's fields, every translation with its fields and its shown switch, and the block tree, including the layout and its fields.

These settings take effect on save instead, outside the draft:

- `enabled`
- the date window
- the channels
- the audience
- "Homepage of" (pages)
- the position (content types ordered by position)
- placements (snippets), which aren't part of the entry at all ([ADR 0005](../adr/0005-placements-take-effect-on-save.md))

Saving one of these fires `EntrySettingsChanged` or `PlacementsChanged` ([Extending](extending.md#events)) and bumps `changedAt`.

### When an entry shows

An entry shows to a shopper when all of these hold. Everywhere the spec says an entry is **visible**, it means this:

1. It has been published.
2. It's enabled.
3. The current time is inside its date window.
4. It's in the current channel.
5. It has a translation in the current locale, and that translation isn't held back.
6. Its audience allows the shopper's customer group.

An entry that isn't visible behaves the same everywhere: its URL returns 404, listings and sources leave it out, a snippet renders nothing, and links to it aren't rendered.

## Built-in content types

The plugin depends on two content types, which can't be disabled:

| Content type | Code | Own URL | Block tree | Layouts |
| --- | --- | --- | --- | --- |
| [Page](../../GLOSSARY.md#entries) | `page` | yes, prefix `''` (the root) | yes, any block type at the top level | the default Page layout ([Layouts and presets](layouts-and-presets.md#shipped-layouts)) |
| [Snippet](../../GLOSSARY.md#entries) | `snippet` | no | yes, any block type at the top level | none |

The shipped collections add Blog post, Blog category, FAQ item and FAQ category ([Collections](collections.md)). Menus aren't a content type in v2.0 ([ADR 0003](../adr/0003-menus-are-entries-with-a-block-tree.md)).

## Content types

A content type is a Doctrine entity subclass of `Entry`, registered as a Sylius resource and marked with `#[ContentType]` ([Extending](extending.md#content-types)). It declares:

- its **code**, which names its discriminator value and prefixes its columns, so it never changes
- whether it has its **own URL**, and its URL prefix
- whether it has a **block tree**, and which block types the top level may hold, with optional min and max
- its **fields**: mapped properties with field attributes, on the entry subclass for shared fields and on its translation subclass for translatable ones
- its **cards**, for listing its entries ([Collections](collections.md#cards))
- its **order**, used by sources that list its entries
- its **category content type**, for a collection with categories
- the field its meta description falls back to
- a **version**, bumped with an upgrader only when stored data can't be read as it is ([Schema changes](#schema-changes))

Each content type with its own URL has one or more layouts of its own ([Layouts and presets](layouts-and-presets.md)). A content type without a block tree is still edited in the builder, with the tree pane hidden ([Builder](builder.md)).

Required entry fields are enforced by validation, not by the database, because subclass columns are nullable. A change to a content type's fields needs a Doctrine migration, and an upgrader only when [Schema changes](#schema-changes) says so.

## Blocks and nesting

A [block](../../GLOSSARY.md#content-structure) has a stable key, a block type, the version of that block type it was saved with, field values, an optional block style, visibility conditions, and one ordered list of children. Blocks nest to any depth. A block type with several areas uses wrapper children, such as Columns > Column.

What a spot in the tree may hold:

- **The top level** may hold the block types the content type allows.
- **A block's children** may be the block types its block type allows, each with an optional min and max. `Allow('*')` allows any block type.
- **A block type can limit its parents.** Column appears only inside Columns, Accordion item only inside Accordion, and Link only inside Link list. A block type that limits its parents is never allowed by `'*'` elsewhere.
- **A block type can need host-page context**, such as `product`. It's offered only where that context is available: in an entry that provides it, or in a snippet that declares it ([Slots](slots.md#snippets-and-their-context)). Context flows down the tree when rendering.

The builder refuses a drop or an insert that breaks these rules. Data that breaks them anyway, such as from an import or after a code change, saves to the draft and is flagged, and publish refuses until it's fixed.

**Repeated items** are child blocks when they have fields of their own, such as slides, accordion items or links. A list of references (products, taxons, entries, media items) is one field. There's no repeater field ([ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md)).

**Snippet references.** A Snippet block shows a snippet inside another block tree and stays in sync with it. "Detach to edit here" replaces the reference with a copy of the snippet's blocks ([Builder](builder.md)). A snippet that would render inside itself, directly or through other snippets, is skipped the second time ([Rendering](rendering.md)).

## Fields

A [field](../../GLOSSARY.md#content-structure) is declared in code with a [field type](../../GLOSSARY.md#content-structure) as a typed property with a field attribute ([Extending](extending.md#field-types)). The same declaration gives the builder its form, gives Twig the hydrated value and validates on save.

**Translatable or shared.** Each field type has a default, and a block type or layout can override it per field with `translatable:`:

- translatable by default: Text, Textarea, Rich text, and a Link's label and external URL
- shared by default: everything else, including references, list fields, numbers, choices, toggles and dates

On a content type, translatability follows where the property sits: on the translation subclass it's translatable, and on the entry subclass it's shared.

**A list field** (Products, Taxons, Entries) is either hand-picked, or filled by a [source](../../GLOSSARY.md#content-structure) of the same kind with a number of items to show ([Sources](sources.md)). Media list fields are always hand-picked.

**A bindable field** (Text, Textarea, Number and Link) can show a [binding](../../GLOSSARY.md#shop-pages) instead of a fixed value, with an optional fallback. Rich text holds bindings as inline tokens ([Slots](slots.md#bindings)).

**Values that don't fit.** A stored value that doesn't fit its field type, such as a string in a Number field, counts as missing. `setono:cms:lint` reports these ([Extending](extending.md#console-commands)).

## References

Content refers to Sylius entities by code and to CMS records by UUID, never by ID or foreign key ([ADR 0002](../adr/0002-references-by-code-and-uuid.md)).

| Referenced record | Stored as | In an export ([Transfer](transfer.md)) |
| --- | --- | --- |
| Product, taxon, channel, customer group, locale, product attribute | its Sylius code | left as a code |
| Entry (in an Entry or Entries field, or a link) | its UUID | a link: not pulled in |
| Snippet (in a Snippet field) | its UUID | a dependency: pulled in |
| Media item | its UUID | a dependency: pulled in |

A reference that no longer resolves is a **broken reference**. Rendering skips it: the field is empty, the list leaves it out and the link isn't rendered. The admin flags it ([Admin](admin.md#problems)). A deleted customer group in a condition or an audience matches nobody ([Targeting](targeting.md)).

Every field type's handler lists the references a value holds, marked as a dependency or a link. Transfer, the media usage index and reference loading before rendering all read that list.

## Stored block tree format

This format is a contract ([ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md)). Code presets are committed in it, and drafts, revisions, exports and editor presets embed it. Block type upgraders handle changes to block fields, but a change to the format itself is a breaking change.

### The tree

The block tree column holds one object:

```json
{
    "layout": {
        "code": "default",
        "version": 1,
        "fields": {
            "showTitle": true
        },
        "translated": {}
    },
    "blocks": []
}
```

| Key | Value |
| --- | --- |
| `layout` | The layout: its code, the version of the layout it was saved with, its field values and their translation state. `null` for a content type without its own URL. |
| `blocks` | The top-level blocks, in order. Empty for a content type without a block tree. |

### A block

```json
{
    "key": "0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f",
    "type": "banner",
    "version": 1,
    "style": "dark",
    "fields": {
        "heading": {
            "da_DK": "Vinterudsalg",
            "en_US": "Winter sale"
        },
        "image": "0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60",
        "fullWidth": true
    },
    "translated": {
        "heading": {
            "da_DK": {
                "from": "en_US",
                "fingerprint": "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"
            }
        }
    },
    "conditions": [
        {
            "kind": "customer_group",
            "negate": true,
            "params": {
                "groups": ["wholesale"]
            }
        }
    ],
    "children": []
}
```

| Key | Value |
| --- | --- |
| `key` | A lowercase UUID, unique within the tree, that never changes while the block exists. Inserting a preset or duplicating a block gives new keys. |
| `type` | The block type's code. |
| `version` | The version of the block type the block was saved with. |
| `style` | The code of the chosen block style, or `null`. |
| `fields` | The field values, keyed by field name (the property name). A field with no value is left out. |
| `translated` | The translation state of translatable fields: field name, then locale, then where that value was translated from. See [Translation state](#translation-state). |
| `conditions` | The visibility conditions, combined with AND. |
| `children` | The child blocks, in order. |

Every key is always present, with `null`, `{}` or `[]` when empty. A block of a type the code no longer has is kept as it is, isn't rendered, and is flagged.

### Field values

What a field stores depends on whether it's translatable:

- **A shared field** stores one value.
- **A translatable field** stores a map from locale code to value. A missing locale, or an empty value, is missing and falls back through the [fallback chain](../../GLOSSARY.md#entries).

Either can instead hold one of these special values. Keys that start with `$` are reserved for them, and no locale code or field value uses one.

| Special value | Shape | Where |
| --- | --- | --- |
| Binding | `{"$bind": "product.name", "$params": {}, "$fallback": null}` | Bindable fields. `$bind` is `<context key>.<property code>`. `$params` holds the bindable property's parameters, such as `{"attribute": "material"}` for Product attribute. `$fallback` is what the field would otherwise store (a value, or a locale map for a translatable field), or `null`. |
| Source | `{"$source": "bestsellers", "$params": {"taxon": "t_shirts"}, "$limit": 8}` | List fields. `$source` is the source's code, `$params` its parameter values, stored as shared field values, and `$limit` how many items show, or the page size when the list paginates. |
| Translation key | `{"$trans": "setono_sylius_cms.preset.hero.heading", "$domain": "messages"}` | Translatable text-like fields, in code preset files only. `$domain` is optional and defaults to `messages`. On insert, each of the entry's locales gets the key's translation if there is one, and the others stay empty. |

### Values per field type

| Field type | Attribute | Stored value |
| --- | --- | --- |
| Text | `Text` | a string |
| Textarea | `Textarea` | a string, newlines kept |
| Rich text | `RichText` | a ProseMirror document: `{"type": "doc", "content": [...]}`. See [Rich text](#rich-text). |
| Number | `Number` | a number |
| Toggle | `Toggle` | `true` or `false` |
| Choice | `Choice` | the chosen value as a string, or an array of strings when `multiple` |
| Date | `Date` | `"2026-11-13"` |
| Date and time | `DateTime` | `"2026-11-13T09:30:00Z"`, always in UTC |
| Link | `Link` | a link. See [Links](#links). |
| Media | `Media` | a media item's UUID |
| Media list | `MediaList` | an array of media item UUIDs |
| Product | `Product` | a product code |
| Products | `Products` | an array of product codes when hand-picked, or a source |
| Taxon | `Taxon` | a taxon code |
| Taxons | `Taxons` | an array of taxon codes when hand-picked, or a source |
| Entry | `Entry` | an entry's UUID |
| Entries | `Entries` | an array of entry UUIDs when hand-picked, or a source |
| Snippet | `Snippet` | a snippet's UUID |
| Code | `Code` | a string (used only by Embed code) |

A project's own field type stores whatever JSON its handler's schema allows ([Extending](extending.md#field-types)).

### Links

A Link field stores:

```json
{
    "target": "entry",
    "ref": "0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e",
    "anchor": "opening-hours",
    "label": {
        "en_US": "Visit us"
    }
}
```

| Key | Value |
| --- | --- |
| `target` | The link target's code: `entry`, `product`, `taxon`, `media`, `url`, or a project's own ([Extending](extending.md#link-targets)). |
| `ref` | What the target refers to: a UUID for `entry` and `media`, a code for `product` and `taxon`, and a locale map of URLs for `url` (including `mailto:` and `tel:`). |
| `anchor` | A fragment without the `#`, or `null`. |
| `label` | A locale map. An empty label shows the target's current name in the shopper's locale. |

A Link field declared `translatable: true` stores a locale map of whole links instead, and each link's `label` and a `url` target's `ref` are then plain strings.

### Rich text

Rich text uses one schema, shared by the field's editor, typing in place and the PHP renderer ([ADR 0008](../adr/0008-the-builder-is-a-react-app-with-a-twig-preview-in-a-same-origin-iframe.md)). It's built from Tiptap's MIT extensions plus two of the plugin's own:

- **Nodes:** `doc`, `paragraph`, `text`, `heading` (levels 2 to 4), `bulletList`, `orderedList`, `listItem`, `blockquote`, `hardBreak`, `horizontalRule`, and `cmsBinding`.
- **Marks:** `bold`, `italic`, `underline`, `strike`, `code`, and `cmsLink`.
- **`cmsBinding`** is an inline atom node: `{"type": "cmsBinding", "attrs": {"property": "product.name", "params": {}, "fallback": "this product"}}`.
- **`cmsLink`** is a mark whose `attrs` are a link without its label: `{"target": "product", "ref": "t_shirt_red", "anchor": null}`, and a plain string `ref` for a `url` target. A link that doesn't resolve renders as plain text.

`ueberdosis/tiptap-php` renders rich text and sanitises it on save. There are no blocks inside rich text in v2.0.

### Visibility conditions

```json
{
    "kind": "date_window",
    "negate": false,
    "params": {
        "from": "2026-11-27T00:00:00Z",
        "until": "2026-11-30T23:59:59Z"
    }
}
```

| Key | Value |
| --- | --- |
| `kind` | The condition kind's code ([Targeting](targeting.md#condition-kinds)). |
| `negate` | `false` for "is one of", `true` for "is not one of". For a date window, inside or outside it. |
| `params` | The condition kind's parameter values, stored as shared field values. Values within one condition combine with OR. |

Page scopes on placements use the same shape ([Slots](slots.md#page-scopes)).

### Translation state

`translated` records, for each translated value, the locale it was translated from and a fingerprint of the source value then ([ADR 0007](../adr/0007-translations-share-one-block-tree.md)):

```json
{ "from": "en_US", "fingerprint": "<lowercase hex SHA-256>" }
```

The fingerprint is the SHA-256 of the source value in [canonical JSON](#canonical-json). A value is **outdated** when its source's current fingerprint differs, and **untranslated** when it's missing in a locale the entry has. "Mark as up to date" rewrites the fingerprint. A value typed without a source, such as the first locale's text, has no entry in `translated`.

### Reserved customer group values

Conditions and audiences hold customer group codes, plus two values that no Sylius code can take, because Sylius codes can't contain `@`:

- `@guest`: not signed in
- `@no_group`: signed in without a group

## Entry format

Drafts, revisions and exports hold a whole entry in one format ([ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md), [ADR 0016](../adr/0016-content-moves-between-environments-as-export-files-and-imports-land-in-drafts.md)). Like the tree format, it's a contract.

```json
{
    "uuid": "0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e",
    "contentType": "blog_post",
    "version": 1,
    "fields": {
        "noindex": false,
        "publishedAt": "2026-11-13T09:00:00Z",
        "featuredImage": "0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60",
        "categories": ["0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b"]
    },
    "translations": {
        "en_US": {
            "shown": true,
            "fields": {
                "title": "Ten ways to style a scarf",
                "slug": "ten-ways-to-style-a-scarf",
                "metaTitle": null,
                "metaDescription": null,
                "excerpt": "From office to weekend."
            },
            "translated": {}
        }
    },
    "tree": {
        "layout": { "code": "article", "version": 1, "fields": {}, "translated": {} },
        "blocks": []
    }
}
```

| Key | Value |
| --- | --- |
| `uuid` | The entry's UUID. |
| `contentType` | The content type's code. |
| `version` | The version of the content type it was saved with. |
| `fields` | The drafted shared fields: the base fields its content type has (`noindex`, and a snippet's `context`) and the content type's own fields on the entry subclass, keyed by property name, in the [field value](#values-per-field-type) encoding. |
| `translations` | One object per locale: `shown` (`false` when held back), `fields` (the translatable fields, including `title`, and `slug`, `metaTitle` and `metaDescription` for a content type with its own URL) and `translated` (field name to [translation state](#translation-state)). |
| `tree` | The [stored block tree](#the-tree). |

The on-save settings aren't part of this format. An export carries them beside it ([Transfer](transfer.md#export-format)).

A share link renders a draft by building an entry from this format that Doctrine doesn't manage.

## Canonical JSON

Wherever the plugin writes this JSON (the tree column, drafts, revisions, preset files, exports and fingerprints), it writes it the same way, so files diff cleanly in Git:

- Keys in the order this spec lists them. Field names in the order their properties are declared. Locale maps, and other maps keyed by codes or UUIDs, sorted by key.
- `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION`.
- Files (presets and exports) are also pretty-printed with four spaces and end with a newline.

## Schema changes

Block types, layouts and content types each have a version, and every stored block, layout and entry records the version it was saved with ([ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md)).

- **Adding a field** needs no version bump. The field takes its default.
- **Removing a field** needs no version bump. Its stored value is ignored and dropped on the next save.
- **Renaming a field, or changing its type or translatability,** needs a version bump and an upgrader from the old version to the new one ([Extending](extending.md#upgraders)).

Upgraders work on the raw arrays of this format, one version step each. They run whenever stored data is read: rows, drafts, revisions, presets and export files. `setono:cms:upgrade` rewrites all stored data in bulk. A block-type, layout or content-type version newer than the code's makes a preset insert or an import refuse ([Transfer](transfer.md#code-the-target-must-have)).
