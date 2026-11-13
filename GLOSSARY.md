# Setono Sylius CMS

A CMS for Sylius stores. Developers at agencies define what content can look like, and merchants compose and publish content in the Sylius admin.

## Language

### Content structure

**Block**:
A typed piece of content with its own field values and an ordered list of child blocks.
_Avoid_: element, component, widget, section

**Block type**:
A developer-defined kind of block. It sets which fields the block has, which block types it may contain, and how it renders.
_Avoid_: block template, component definition

**Block tree**:
The ordered, nested blocks that make up the body of an entry.
_Avoid_: content, page builder data

**Preset**:
One or more saved blocks, with everything in them, that are copied when inserted and never kept in sync with where they came from. Developers ship presets and editors save their own, and a preset can be a starting point for new entries of one content type.
_Avoid_: layout preset, pattern, template, entry template

**Field**:
A named, typed value on a block, an entry or a layout, such as a heading, an image, a link or a list of products. A field is either translatable, with a value per translation, or shared by all translations.
_Avoid_: property, attribute, setting

**Field type**:
A kind of field, such as text, rich text, an image, a link or a list of products, which sets what a field holds and how editors enter it. The plugin ships field types, and developers can add their own.
_Avoid_: input type, widget, form type

**Source**:
A developer-defined way to fill a field with a list of items of one kind when the content is shown, such as the products in a taxon or the current product's associations.
_Avoid_: provider, data source, feed

### Entries

**Content type**:
A developer-defined kind of entry. It sets which fields the entry has, whether it has a block tree and which block types its top level may hold, and whether it has its own URL.
_Avoid_: model, schema, collection type

**Entry**:
A single piece of content of one content type, such as a page, a blog post or a store location.
_Avoid_: document, item, record, node

**Translation**:
An entry's content in one locale. An entry has one block tree shared by all its translations, and shoppers see it only in locales where it has a translation that isn't held back.
_Avoid_: localization, language version, locale variant

**Fallback chain**:
The locales a translatable field falls back through, in order, when it's empty in the current locale. It ends at the store's fallback locale. A binding's fallback is a different thing.
_Avoid_: default locale, locale fallback

**Slug**:
The part of an entry's URL that editors set for each translation, such as `about-us` or `about/team`. No two entries have the same URL in one channel and locale.
_Avoid_: path, handle, permalink, URL key

**Page**:
An entry of the built-in content type for standalone pages with their own URL and a block tree.
_Avoid_: CMS page, static page

**Homepage**:
The page a channel shows at its root URL in place of the store's own homepage. A channel has at most one.
_Avoid_: front page, start page, landing page

**Built-in content type**:
A content type the plugin itself depends on: Page or Snippet. Collections never use a built-in content type.
_Avoid_: core type, system type

**Collection**:
All entries of one content type other than the built-in ones, such as the blog, the FAQ or the store locations.
_Avoid_: section, channel, list

**Snippet**:
An entry of the built-in content type for named, reusable block trees without their own URL. A snippet is shown in slots, or referenced from other block trees, and stays the same wherever it's used.
_Avoid_: shared block, global block, fragment, partial, synced pattern

**Layout**:
A developer-defined frame around the block tree of an entry shown at its own URL. It shows the entry's fields and its own, and can contain slots. Each content type with its own URL has one or more layouts of its own.
_Avoid_: developer layout, page template, theme

### Shop pages

**Slot**:
A developer-defined, named place in a shop page or a layout where editors show snippets. It shows every placement that matches, in the order editors set, unless it's capped, and then only the first ones that match show.
_Avoid_: zone, area, region, block position, template event, hook

**Placement**:
The showing of one snippet in one slot, limited by a page scope.
_Avoid_: assignment, slot item

**Page scope**:
The conditions on host-page context that decide which shop pages a placement shows on, such as products in one taxon or a single product.
_Avoid_: page rule, display rule, targeting

**Host-page context**:
The page-specific objects a shop page or an entry makes available to the content shown in it, such as the current product on a product page. The channel, locale and customer are available everywhere and aren't part of it.
_Avoid_: page variables, template context

**Binding**:
A field value, or a token inside rich text, that shows a property of the host-page context instead of a fixed value, such as the current product's name.
_Avoid_: dynamic source, merge tag, placeholder, variable

### Visibility

**Visibility condition**:
A rule on a block that hides it unless the shopper or the page matches, such as "customer group is Wholesale", "locale is not German" or "product is in the Shoes taxon". A hidden block hides its children. A page scope is made of the same kinds of conditions.
_Avoid_: targeting rule, display rule, filter

**Audience**:
The customers an entry shows to: everyone, only some customer groups, or everyone except some. Guests, and signed-in customers without a group, count as groups of their own here.
_Avoid_: target group, segment, permissions

### Media

**Media library**:
All the media items a store has, shared by every channel.
_Avoid_: asset manager, file manager, DAM

**Media item**:
One uploaded file in the media library, an image, a video or a document, with its metadata. Product and taxon images aren't media items.
_Avoid_: asset, file, attachment, upload, media (for a single item)

**Focal point**:
The point of an image that must stay in view wherever the image is cropped. It belongs to the media item, so every use of the image crops around the same point.
_Avoid_: hotspot, crop center

### Editing

**Builder**:
The full-screen editor where an entry is composed, with its block tree, a live preview of the shop page and the fields of what's selected.
_Avoid_: page builder, visual editor, designer, editor (an editor is a person)

### Publishing

**Draft**:
An entry's saved changes, which shoppers don't see until they're published. An entry has at most one draft, shared by everyone who edits it.
_Avoid_: pending changes, unpublished version

**Publish**:
To make an entry's draft the content shoppers see, either now or as a scheduled publish. Placements, an entry's date window and whether it's enabled don't wait for it: they take effect on save.
_Avoid_: release, go live, deploy

**Scheduled publish**:
A publish of an entry's draft set for a chosen time. The draft can't be edited until the publish happens or is cancelled.
_Avoid_: scheduled draft, release

**Date window**:
The span from an optional start to an optional end in which an entry or a block shows. It decides only when published content shows, never what's published.
_Avoid_: schedule, publish window, publish date

**Revision**:
An entry's published content as it was at one publish, kept so editors can preview it and restore it into the draft.
_Avoid_: version, snapshot, history entry

**Share link**:
A revocable link that shows one entry's draft in the shop, in one channel and locale, to anyone who has it.
_Avoid_: preview link, public preview, preview token

### Transfer

**Transfer**:
Moving content from one environment of a store to another, such as from staging to production, by exporting it from one and importing it into the other. A transfer creates and updates content, but never deletes it.
_Avoid_: sync, migration, deployment

**Export**:
The files a transfer carries: the entries, editor presets and media items someone picked, plus the snippets and media items they use.
_Avoid_: dump, backup, package

**Import**:
The step of a transfer that brings an export into a store. Imported content goes into each entry's draft, and shows only once it's published.
_Avoid_: restore, load
