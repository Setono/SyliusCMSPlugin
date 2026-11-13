# Sources

How list fields are filled when content is shown: hand-picked lists, the shipped sources, the visible queries they build on, paging, and Bestsellers.

- **Terms:** [source, field](../../GLOSSARY.md#content-structure), [host-page context](../../GLOSSARY.md#shop-pages), [audience](../../GLOSSARY.md#visibility), [collection, category](../../GLOSSARY.md#entries).
- **Decisions:** [ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md) (sources return a Pagerfanta adapter), [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (sources are classes registered by attribute), [ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md) (paginated listings), [ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md) and [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (what SQL filters on).

## List fields

A list field (Products, Taxons or Entries) is filled one of two ways, chosen in the builder's "Filled by" select ([Content model](content-model.md#fields)):

- **Hand-picked**, offered first. The editor picks items and orders them. It's stored as a plain array of codes or UUIDs. Hand-picked isn't a registered source and can't be disabled.
- **A source** of the same kind, such as Bestsellers. The editor picks the source, fills in its parameters inline, and sets how many items show. It's stored as `{"$source": …, "$params": …, "$limit": …}` ([Content model](content-model.md#field-values)).

`$limit` is how many items show, or the page size of a paginated Entries block. A new list starts at the field's `limit` option ([Field types](extending.md#field-types)). A source has no limit of its own: the list field that holds it decides.

Next to "Filled by", the builder shows how many items the list yields right now, in the preview's channel, locale, Shown to and Date.

A field's `sources` option narrows which sources it offers, and `sources: false` allows only hand-picking. A source's own parameter fields are always hand-picked.

## The contract

A source is a class with `#[AsSource]` that implements `SourceInterface` ([Extending](extending.md#sources)). Its `resolve(params, context)` returns a Pagerfanta `AdapterInterface`: a count and a slice, neither run until asked. Every source follows these rules:

- **It takes no limit and no offset.** The caller pages it. A list that doesn't paginate calls `getSlice(0, $limit)` and never counts. The paginated Entries block wraps the adapter in a `Pagerfanta` ([Collections](collections.md#pagination)).
- **Its order is stable and ends in a unique tie-breaker**, such as the ID, so pages never overlap or skip items.
- **A source built on Doctrine returns a `QueryAdapter`**, so paging, counting and visibility filtering happen in SQL. A source over a list uses Pagerfanta's array adapters.
- **It builds on the [visible queries](#visible-queries).** They're provided, not enforced, but shipped sources use them and agency sources should.
- **It declares the context keys it needs** (`needs:`). The builder offers it only where they're available, and it yields nothing when one is missing at render time. A source that fits only some content types as `entry` context also implements `EntryContextAwareInterface` ([Extending](extending.md#sources)).

Hand-picked lists follow the same rules: their picks are filtered through the same visible queries and keep the editor's order, so their counts are right too.

**Every source can page**, whether it lists entries, products or taxons. In v2.0 only the Entries block paginates. The Products and Taxons blocks show the first `$limit` items, and a later API can page them without a contract change.

**A source that's no longer registered**, or one disabled in `setono_sylius_cms.disabled.sources`, isn't offered to editors. Lists already filled by a disabled source keep resolving through it. A list filled by a source the code no longer has yields nothing, and the admin flags it.

Listings and sources report no next date boundary in v2.0, because nothing is cached ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)).

## Visible queries

The plugin provides one query each for the entries, products and taxons a shopper may see ([Extending](extending.md#sources)). Each returns a Doctrine `QueryBuilder` with no `ORDER BY`. It reads the shopper, channel, locale and time from the render context, so the preview's forced Shown to and Date apply to listings too ([Targeting](targeting.md)).

| Query | Root alias | Keeps |
| --- | --- | --- |
| `VisibleEntriesQueryInterface` | `entry` | One content type's entries that are [visible](content-model.md#when-an-entry-shows): published, enabled, inside their date window, in the channel, with a translation in the locale that isn't held back, and in the shopper's audience. |
| `VisibleProductsQueryInterface` | `product` | Products that are enabled, in the channel and translated into the locale, mirroring Sylius's own shop listing. |
| `VisibleTaxonsQueryInterface` | `taxon` | Taxons that are enabled. |

Published content is the only content in the entry rows, so the entries query needs no stage filter ([ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md)). The audience is a SQL condition on the audience mode and its group codes, with `@guest` and `@no_group` for shoppers without a group ([ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md)).

## Shipped sources

Every shipped source can be disabled with `setono_sylius_cms.disabled.sources`. Parameter fields are listed by property name and field type, and are all shared.

### Product sources

| Code | Label | Needs | Parameters | Order |
| --- | --- | --- | --- | --- |
| `products_in_taxon` | In taxon | | `taxon` (Taxon, required), `includeChildren` (Toggle, off), `sort` (Choice: `position` (default), `newest`, `price`, `name`) | `position`: the product's position in the taxon. `newest`: newest first. `price`: the lowest price of its enabled variants in the channel, lowest first. `name`: the translated name, A to Z. Then the ID. |
| `bestsellers` | Bestsellers | | `taxon` (Taxon, optional), `includeChildren` (Toggle, off) | the score, highest first, then the ID. See [Bestsellers](#bestsellers). |
| `newest_products` | Newest | | none | newest first, then the ID |
| `product_associations` | Associations | `product` | `associationType` (Choice of the store's association type codes, optional: empty means every type) | the associated product's ID |
| `same_taxon_products` | Same taxon | `product` | none | the product's position in the host page's product's main taxon, then the ID. It leaves that product out, and yields nothing when the product has no main taxon. |

### Taxon sources

| Code | Label | Needs | Parameters | Order |
| --- | --- | --- | --- | --- |
| `taxon_children` | Children of a taxon | | `taxon` (Taxon, required) | the taxon's position, then the ID |

### Entry sources

| Code | Label | Needs | Parameters | Order |
| --- | --- | --- | --- | --- |
| `entries_from_collection` | From a collection | | `contentType` (Choice of the collections' content type codes, required), `categories` (Entries of that collection's category type, optional) | the content type's order, then the ID |
| `entries_in_this_category` | In this category | `entry`, a category | none | the listed content type's order, then the ID |
| `related_entries` | Related | `entry`, of a collection with categories | none | newest first, then the ID |

- **From a collection** lists one content type's entries, optionally only those in any of the chosen categories. It has no limit of its own.
- **In this category** lists the entries of the collection whose category type is the host-page entry's content type, that are in that category. It's offered only where a category is the host-page context, which makes category archives work ([Collections](collections.md#categories)).
- **Related** lists entries of the host-page entry's content type that share at least one category with it, leaving that entry out. It's offered only on entries of a collection with categories. "Newest first" is the content type's order when that order is a date descending, such as Blog post's `publishedAt`, and `createdAt` descending otherwise.
- **Category filters** join the indexed (entry, field, UUID) reference table, so they page in SQL on MySQL and PostgreSQL ([Collections](collections.md#categories)).
- **Each content type declares its order** in `#[ContentType(order: …)]` ([Extending](extending.md#content-types)). Hand-picked always keeps the editor's own order.

## Bestsellers

The Bestsellers source is swapped at its calculation, not at its lookup ([ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md)).

- **A calculator** implements `BestsellersCalculatorInterface` and yields a score for each product code in a channel ([Extending](extending.md#bestsellers)).
- **The default calculator** scores each product by how many paid orders contained it, in the channel, over a rolling window: `setono_sylius_cms.bestsellers.window`, 30 days (`P30D`) by default. An order counts when its checkout completed inside the window and its payment state is paid.
- **`setono:cms:bestsellers:compute [--channel=CODE]`** runs the calculator for every channel, or one, and replaces that channel's scores in the plugin's table. The project runs it from cron. When it finishes, it dispatches `BestsellersComputed` with the channel codes ([Extending](extending.md#events)).
- **The source** joins the visible-products query against the scores for the current channel, with its taxon filter and its order in SQL, so a list of 8 never comes up short while there are 8 scored products. Products with no score are left out.
- **Until the command first runs**, the table is empty, and so is every Bestsellers list.
- **A project with analytics data** implements the calculator and aliases the interface to it. The command, the table and the source stay the same.

## Paging and rendering

- **Only the Entries block paginates**, and only in an entry with its own URL ([Collections](collections.md#pagination)).
- **The paginated block's source is counted before the entry renders**, so a page past the end returns 404 before any output ([Rendering](rendering.md)).
- **Sources resolve lazily.** A list field's `ItemList` runs its adapter when a template first reads it ([Field types](extending.md#field-types)), unlike the references in a block tree, which load in one pass before rendering.
