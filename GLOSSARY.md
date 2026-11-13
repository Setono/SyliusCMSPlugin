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
A saved block tree, or part of one, that is copied when inserted and never kept in sync with where it came from. Developers ship presets, and editors save their own.
_Avoid_: layout preset, pattern, template

**Field**:
A named, typed value on a block or an entry, such as a heading, an image, a link or a list of products. A field is either translatable, with a value per translation, or shared by all translations.
_Avoid_: property, attribute, setting

**Source**:
A developer-defined way to fill a field with a list of items of one kind when the content is shown, such as the products in a taxon or the current product's associations.
_Avoid_: provider, data source, feed

### Entries

**Content type**:
A developer-defined kind of entry. It sets which fields the entry has, whether it has a block tree, and whether it has its own URL.
_Avoid_: model, schema, collection type

**Entry**:
A single piece of content of one content type, such as a page, a blog post or a store location.
_Avoid_: document, item, record, node

**Translation**:
An entry's content in one locale. An entry has one block tree shared by all its translations, and shoppers see it only in locales it has a translation for.
_Avoid_: localization, language version, locale variant

**Fallback chain**:
The locales a translatable field falls back through, in order, when it's empty in the current locale. It ends at the store's fallback locale. A binding's fallback is a different thing.
_Avoid_: default locale, locale fallback

**Page**:
An entry of the built-in content type for standalone pages with their own URL and a block tree.
_Avoid_: CMS page, static page

**Built-in content type**:
A content type the plugin itself depends on: Page, Snippet or Menu. Collections never use a built-in content type.
_Avoid_: core type, system type

**Collection**:
All entries of one content type other than the built-in ones, such as the blog, the FAQ or the store locations.
_Avoid_: section, channel, list

**Snippet**:
An entry of the built-in content type for named, reusable block trees without their own URL. A snippet is shown in slots, or referenced from other block trees, and stays the same wherever it's used.
_Avoid_: shared block, global block, fragment, partial, synced pattern

**Menu**:
An entry of the built-in content type for navigation, whose block tree holds links, groups of links and, in mega menus, other blocks.
_Avoid_: navigation, menu tree

**Layout**:
A developer-defined frame around an entry's block tree that shows the entry's fields and can contain slots. Each content type offers one or more layouts.
_Avoid_: developer layout, page template, theme

### Shop pages

**Slot**:
A developer-defined, named place in a shop page or a layout where editors show snippets.
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
