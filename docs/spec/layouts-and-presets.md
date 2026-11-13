# Layouts and presets

Layouts, the frames around entries shown at their own URL, and presets, the saved blocks editors insert and start entries from.

- **Terms:** [layout, entry, content type](../../GLOSSARY.md#entries), [preset, block, field, block tree](../../GLOSSARY.md#content-structure), [slot](../../GLOSSARY.md#shop-pages), [builder](../../GLOSSARY.md#editing).
- **Decisions:** [ADR 0011](../adr/0011-layouts-are-interchangeable-frames-with-fields-in-the-block-tree.md) (layouts are interchangeable frames, with fields in the block tree), [ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md) (code presets are JSON files in the stored block-tree format), [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (layouts are classes whose typed properties are their fields).

## Layouts

A [layout](../../GLOSSARY.md#entries) is declared in code with `#[Layout]` ([Extending](extending.md#layouts)).

- **Only content types with their own URL have layouts**: Page, Blog post, Blog category, and any project content type with a prefix. Snippets and entries without a URL, such as FAQ items, render their block tree with no frame wherever they're shown. A snippet that needs a frame uses a wrapper block, such as a Group.
- **Each layout belongs to one content type**, so its fields can be checked against that type. Layouts share markup through Twig includes and PHP inheritance. Projects can add layouts to any content type with a URL, including the shipped ones.
- **Exactly one layout per content type is its default** (`default: true`).
- **A layout sets no rules on blocks.** The content type decides what the top level of the tree may hold, so every layout of a content type accepts any tree of that type, and switching layout never touches the tree.
- **A layout extends the shop layout** through `@SetonoSyliusCMSPlugin/layout/base.html.twig`, which prints the head tags ([Layout templates](extending.md#layout-templates)).
- **A layout can contain slots.** Its template renders them with `cms_slot(id, {entry: entry})`. They're declared in config like any slot, with no template event, and provide `entry` ([Slots](slots.md#rendering-a-slot)).

### Layout fields

A layout can have fields of its own for presentation options, such as "Hide the title" or "Transparent header". They're the layout class's typed properties with field attributes.

- **Their values are stored in the block tree**, in the tree's `layout.fields`, with their translation state in `layout.translated` ([Content model](content-model.md#the-tree)). So they're drafted, translated, upgraded and transferred like block fields.
- **They're versioned like a block type's fields.** Renaming one, or changing its type or translatability, needs a version bump and an upgrader ([Upgraders](extending.md#upgraders)).
- **Rule of thumb:** a field that every layout of a content type uses, or that gets queried, belongs to the content type as a column. A presentation option that one layout uses belongs to the layout ([ADR 0011](../adr/0011-layouts-are-interchangeable-frames-with-fields-in-the-block-tree.md)).
- Layout field values can't be filtered or sorted on in SQL.

### Shown entry fields

A layout declares which entry fields its template prints, in `#[Layout(shows: […])]`.

- The builder marks the entry's other fields "Not shown in this layout" ([Builder](builder.md)).
- The preview reloads the whole page only when a shown entry field, or a layout field, changes. Block changes are patched in.

### Picking and switching

- **Editors pick the layout** in the create dialog ([Admin](admin.md#create-dialog)) or in the entry settings at the top of the builder's tree. The picker shows each layout's thumbnail, and is hidden when the content type has only one layout.
- **The layout choice is drafted.** It's stored in the tree's `layout.code`, so nothing changes for shoppers until the entry is published.
- **Switching leaves the block tree and the entry fields as they are.** Values in entry fields the new layout doesn't show are kept.
- **Layout field values carry over** to fields of the new layout with the same name and field type, together with their translation state. The rest are dropped. Undo in the builder brings them back.

### A removed layout

When a layout is removed from code, its entries keep its code in their tree.

- They render with the content type's default layout, ignoring the stored layout field values.
- The admin flags them as a warning, in the builder's entry settings and on the entry's overview page ([Admin](admin.md#problems)).
- Picking a layout clears the flag. Values carry over as in [Picking and switching](#picking-and-switching).

## Shipped layouts

| Content type | Code | Label | Fields | Shows | What it renders |
| --- | --- | --- | --- | --- | --- |
| Page | `default` | Default | `showTitle` (Toggle, on by default) | `title` | The title, when `showTitle` is on, then the tree edge to edge, with no container of its own. |
| Blog post | `article` | Article | none | `title`, `publishedAt`, `categories`, `featuredImage` | Contained width: the title, the date, links to the post's categories and the featured image above the tree. |
| Blog category | `default` | Default | none | `title`, `description` | The title and the description above the tree. |

- Their templates are `@SetonoSyliusCMSPlugin/layout/<content type code>/<layout code>.html.twig`.
- **Contained and full width on the default Page layout.** The tree sits in `cms-tree`, which contains each top-level block to the shop's content width. A Group or a Banner with `fullWidth` on reaches the edges, so a page can mix full-width and contained blocks without a second layout ([Blocks](blocks.md#class-names)). Shop pages have their own containers, so a full-width block only reaches the edges where its surroundings allow.
- The shipped layouts have no slots of their own. Agencies add layouts with slots.

## Presets

A [preset](../../GLOSSARY.md#content-structure) is one or more blocks, each with its children. Developers ship code presets, and editors save their own. Both are copies that never sync with where they came from.

### Where a preset is offered

The builder's block picker has a Presets tab, and + between blocks offers presets too ([Builder](builder.md)). A preset is offered at a spot when:

- every one of its top-level blocks is allowed there
- inserting it keeps the spot within its min and max
- the context its blocks, sources and bindings need is available there

Presets left out are named with the reason, the same way block types are, such as "needs a product page".

### What inserting copies

Inserting a preset copies everything:

- the block types and their nesting
- every field value in every locale, with its translation state
- visibility conditions, block styles and source settings
- bindings, with their fallbacks
- snippet references, which stay references
- media and entity references, which are kept

On insert:

- **Each block gets a new key.**
- **Values for locales the entry doesn't have are dropped.**
- **Translation keys** in code presets are resolved: each of the entry's locales gets the key's translation if the translator has one, and the other locales stay empty, so they count as untranslated in the completeness counts ([Localization](localization.md#completeness-and-outdated-flags)).
- **Upgraders run** on any block saved with an older block-type version ([Content model](content-model.md#schema-changes)). A block saved with a newer version than the code's makes the insert refuse, with a notice.

### Starting points

A preset can be a **starting point** for one content type. It then also remembers a layout and that layout's field values.

- **Creating an entry** offers Blank plus the content type's starting points, shipped and saved by editors ([Admin](admin.md#create-dialog)).
- **The default.** A developer can mark one code preset as the content type's default starting point (`"default": true`), which the create dialog preselects. Without one, Blank is preselected. Editors can't change the default in v2.0, and editor presets are never the default.
- **Entry fields start empty**, such as the title, the slug and the SEO fields. The entry's channels, audience and other settings start as for any new entry.
- **The layout** from the starting point is used, with its field values. When that layout no longer exists, the content type's default layout is used and the layout field values are dropped.

A starting point is a role a preset plays, not a term of its own.

## Preset file format

Code presets and editor presets use one format, built on the [stored block tree format](content-model.md#stored-block-tree-format) ([ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md)). Exports carry editor presets in it ([Transfer](transfer.md#export-format)). Like the tree format, it's a contract, written as [canonical JSON](content-model.md#canonical-json).

```json
{
    "format": 1,
    "name": {"$trans": "setono_sylius_cms.preset.hero.name"},
    "category": {"$trans": "setono_sylius_cms.preset_category.banners"},
    "description": {"$trans": "setono_sylius_cms.preset.hero.description"},
    "thumbnail": null,
    "startingPoint": null,
    "blocks": [
        {
            "key": "0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f",
            "type": "banner",
            "version": 1,
            "style": null,
            "fields": {
                "heading": {"$trans": "setono_sylius_cms.preset.hero.heading"},
                "fullWidth": true
            },
            "translated": {},
            "conditions": [],
            "children": []
        }
    ]
}
```

| Key | Value |
| --- | --- |
| `format` | `1`. A change to the format itself bumps it and is a breaking change. |
| `uuid` | Editor presets only, right after `format`: the preset's UUID. |
| `changedAt` | Editor presets only, right after `uuid`: when it last changed, in UTC ([Transfer](transfer.md#stale-records)). |
| `name` | A string, or a translation key as `{"$trans": …, "$domain": …}`. Required. |
| `category` | A string, a translation key, or `null`. |
| `description` | A string, a translation key, or `null`. |
| `thumbnail` | A public path to an image, or `null`. Used for starting points in the create dialog. Code presets only. |
| `startingPoint` | `null` for a block preset, or the object below. |
| `blocks` | The preset's top-level blocks, in order, in the [stored block format](content-model.md#a-block). |

`startingPoint`:

```json
{
    "contentType": "page",
    "layout": "default",
    "layoutVersion": 1,
    "layoutFields": {"showTitle": false},
    "layoutTranslated": {},
    "default": false
}
```

| Key | Value |
| --- | --- |
| `contentType` | The content type's code. |
| `layout`, `layoutVersion` | The layout's code and the version its fields were saved with, or `null` for a content type without layouts. |
| `layoutFields` | The layout's field values, in the [field value](content-model.md#field-values) encoding. `{}` when there are none. |
| `layoutTranslated` | Their [translation state](content-model.md#translation-state), as in the tree's `layout.translated`. `{}` when there's none. |
| `default` | `true` for the content type's default starting point. Code presets only. |

## Code presets

Code presets are JSON files in the [preset file format](#preset-file-format), in directories that projects and bundles register ([ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md)).

- **Registering.** A project lists directories under `setono_sylius_cms.presets.paths`, and a bundle prepends its own ([Extending](extending.md#configuration)). Every `*.json` file directly in a registered directory is a code preset.
- **Codes.** A code preset's code is its file name without `.json`, such as `hero`. Codes are unique across every registered directory, and a duplicate fails the container build. `setono_sylius_cms.disabled.presets` switches off code presets by code.
- **Composing one.** Developers don't write presets by hand. They compose one in the builder, save it as an editor preset, and export it: from the Presets tab, which downloads the file, or with `setono:cms:preset:export <preset> <dir>`, which writes `<dir>/<code>.json`, where the code is the preset's name in snake case ([Console commands](extending.md#console-commands)).
- **Media is left out on export.** Media and Media list fields are left empty, because media items exist only in one environment's library. The block's placeholder shows in their place ([Blocks](blocks.md#empty-images)). Editor presets, which stay in the store, keep their media references.
- **Translation keys.** An exported file holds text as locale maps. A plugin that doesn't know a store's locales replaces those values, and the name, category and description, with translation keys, `{"$trans": "…"}`, and ships the translations in its translation files.
- **Validating.** `setono:cms:preset:validate` checks every file in every registered directory, or the paths given, and exits non-zero on any error, for CI. It checks the format, that every block type exists, that no block-type version is newer than the code's, the nesting rules, that field values fit their field types, and that a starting point's content type and layout exist.
- **Read-only.** Code presets are read-only in the admin and marked as coming from code. To change one, a developer inserts it, edits it, saves it as an editor preset and exports it again.
- **Old files keep working.** Each block records its block-type version, so upgraders bring old preset files up to date when they're inserted.

## Editor presets

Editors save their own presets from the builder ([Builder](builder.md)).

- **Saving.** ★ saves the selected block, or a ⌘-click selection, as a preset.
  - A selection under different parents is saved in tree order as the preset's top-level blocks.
  - A selected block inside another selected block counts once, as part of its parent.
  - Saving the entry's whole tree, from the entry settings, makes a starting point for the entry's content type, with its layout and layout field values.
- **Organising.** Each preset has a name, an optional category and an optional description. The category is free text, suggested from the categories in use, and code presets use the same categories.
- **The Presets tab** groups presets by category, has search, and shows a live render of each preset in the current preview context instead of a stored image.
- **Changing.** Editors rename, recategorise or delete a preset in the Presets tab, or on the Presets screen in the Content hub ([Admin](admin.md#presets)). To change its blocks, they insert it, edit it, then choose "Save as preset" and "Replace <name>". There's no separate preset editor in v2.0.
- **Saving takes effect at once.** Presets aren't entries, so they have no drafts or revisions. Changing or deleting a preset never changes the entries it was inserted into.
- **Media.** The media usage index counts the media items editor presets use, so a media item can't be deleted while a preset uses it ([Media](media.md)).
- **Transfer.** Editor presets can be picked for export, and travel with the media items they use ([Transfer](transfer.md)).

## Presets with invalid blocks

A preset can hold blocks that are no longer valid, such as after a block type was removed from code or a field changed.

- **The Presets tab, the Presets screen and the create dialog mark the preset** and say why. A starting point with blocks of removed types is disabled in the create dialog.
- **On insert, blocks of a type the code no longer has are left out**, with a notice. So is a disabled block type's block, which can't be added.
- **Other problems are inserted and flagged** like any invalid block, and publish refuses until they're fixed ([Publishing](publishing.md)).

## Shipped code presets

All text in the shipped presets is translation keys. No starting point for Page is the default, so the create dialog preselects Blank.

| Code | Kind | Category | Holds |
| --- | --- | --- | --- |
| `landing_page` | Page starting point, `default` layout | Pages | a Banner carousel, a Bestsellers carousel, Three USPs and a Rich text |
| `content_page` | Page starting point, `default` layout | Pages | a Heading and a Rich text |
| `blog_page` | Page starting point, `default` layout | Pages | see [Collections](collections.md#shipped-layouts-starting-points-and-presets) |
| `faq_page` | Page starting point, `default` layout | Pages | see [Collections](collections.md#shipped-layouts-starting-points-and-presets) |
| `blog_category_archive` | Blog category starting point, the default | Blog | see [Collections](collections.md#shipped-layouts-starting-points-and-presets) |
| `banner_carousel` | block preset | Banners | a Carousel of three Banners |
| `hero` | block preset | Banners | a full-width Banner with two Buttons, primary and secondary |
| `campaign_with_fallback` | block preset | Banners | a First match holding a Banner with a `date_window` condition, then a default Banner |
| `image_and_text` | block preset | Content | Columns: a Column with an Image, and a Column with a Heading, a Rich text and a Button |
| `three_usps` | block preset | Content | Columns with three Columns, each an Image, a Heading and a Rich text |
| `faq` | block preset | Content | an Accordion of three Accordion items |
| `bestsellers_carousel` | block preset | Products | a Products block with `display: carousel`, filled by `bestsellers` |
| `related_products` | block preset | Products | a Products block filled by `product_associations` of every type, so it's offered only where a product is available |
| `footer_links` | block preset | Navigation | Columns of Link lists |
| `latest_posts` | block preset | Blog | see [Collections](collections.md#shipped-layouts-starting-points-and-presets) |
| `related_posts` | block preset | Blog | see [Collections](collections.md#shipped-layouts-starting-points-and-presets) |

The images in shipped presets are empty and show the placeholder until an editor picks one ([Blocks](blocks.md#empty-images)).
