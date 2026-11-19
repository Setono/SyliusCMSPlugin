# Collections

The Blog and the FAQ, categories, and how a collection's entries are listed through pages, the Entries block and cards.

- **Terms:** [collection, category, card, entry, content type, page](../../GLOSSARY.md#entries), [source](../../GLOSSARY.md#content-structure).
- **Decisions:** [ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md) (categories are entries, and collections are listed by pages), [ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md) (sources page), [ADR 0002](../adr/0002-references-by-code-and-uuid.md) (references by UUID), [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (content types are Sylius resources).

## What ships

- **The Blog and the FAQ ship, and both are on after install.** Store locations don't ship: they need coordinates, opening hours and a map provider, so agencies build them as their own collection.
- **Disabling.** `setono_sylius_cms.disabled.content_types` switches off a collection as a whole: `blog_post` with `blog_category`, or `faq_item` with `faq_category`. That hides its content types from the admin, the builder, sources and routing. Switching off some but not all of a collection's content types fails the container build, naming the one to add, since the other would otherwise be listed as a collection of its own. Its columns stay mapped either way, so the schema never depends on the setting ([Extending](extending.md#content-types)).
- **Extending.** A project that wants more on a shipped content type, such as an author on blog posts, extends its model ([Extending](extending.md#content-types)). There's no author in v2.0.
- **Not in v2.0:** date archives, RSS feeds and tags.

## Shipped content types

Every entry also has the base fields in [Content model](content-model.md#entries): a title per translation, and for a content type with its own URL, a slug, a meta title, a meta description and `noindex`. Entries without their own URL have no slug and no SEO fields.

| Content type | Code | Own URL | Block tree | Layouts | Order | Cards |
| --- | --- | --- | --- | --- | --- | --- |
| Blog post | `blog_post` | yes, prefix `blog` | yes, `Allow('*')` | `article` (default) | `publishedAt` descending | `grid`, `carousel` |
| Blog category | `blog_category` | yes, prefix `blog/category` | yes, `Allow('*')`, holds its post list | `standard` (default) | `createdAt` descending | none |
| FAQ item | `faq_item` | no | no | none | `position` | `accordion` |
| FAQ category | `faq_category` | no | no | none | `position` | none |

Their fields, beyond the base fields:

| Content type | Field | Field type | Translatable | Notes |
| --- | --- | --- | --- | --- |
| Blog post | `publishedAt` | Date and time, labelled "Date" | no | See [The blog post's date](#the-blog-posts-date). |
| Blog post | `featuredImage` | Media (`image`) | no | |
| Blog post | `categories` | Entries (`contentType: BlogCategory`, `indexed: true`, `sources: false`) | no | |
| Blog post | `excerpt` | Textarea | yes | Its `descriptionField`, so the meta description falls back to it. |
| Blog category | `description` | Textarea | yes | Its `descriptionField`. |
| FAQ item | `answer` | Rich text | yes | The title is the question. |
| FAQ item | `categories` | Entries (`contentType: FaqCategory`, `indexed: true`, `sources: false`) | no | |
| FAQ category | | | | Title only. |

- Blog post declares `categoryType: BlogCategory`, and FAQ item declares `categoryType: FaqCategory`.
- The models, translation models and resource aliases are listed in [Extending](extending.md#shipped-content-types).
- Shared fields are columns on the entry subclass, prefixed with the content type's code, such as `blog_post_published_at`. Translatable fields are columns on the translation subclass, such as `blog_post_excerpt`.
- FAQ items are dragged into order in their admin list ([Admin](admin.md#entry-lists)). Their position takes effect on save ([Content model](content-model.md#whats-drafted-and-what-takes-effect-on-save)).

### The blog post's date

A blog post's date is a field of its own, `publishedAt`, stored as a column:

- The first publish fills it when it's empty. A later publish never changes it, but fills it again when an editor emptied it.
- Editors can change it, such as to backdate a post.
- It moves with the entry in a transfer, so a post keeps its date when it's published in another environment.
- It isn't the "Show from" date. The date window decides only when published content shows ([Publishing](publishing.md)).

## Categories

A [category](../../GLOSSARY.md#entries) is an entry of the category content type its collection declares, such as Blog category for the Blog ([ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md)).

- **Categories are entries.** They get what their content type gives them: Blog categories have translations, slugs, SEO fields, channels, an audience, drafts and a block tree. FAQ categories have a title per translation and nothing else.
- **Categories are flat.** There's no category tree.
- **An entry can be in several categories.** Its `categories` field holds their UUIDs, in the editor's order.
- **The reference table.** Publishing writes the UUIDs of every `Entries` field declared with `indexed: true` to an indexed table of (entry, field, UUID). Listings and sources filter on it with one indexed join, on MySQL and PostgreSQL. The table mirrors published content only: a draft's categories live in the draft until it's published.
- **A deleted category** leaves dangling UUIDs on its entries. Rendering skips them like any [broken reference](content-model.md#references), and the admin flags them.
- **A category picker** in the builder lists the collection's categories ([Admin](admin.md)).

## Listings

No collection has listing routes in code. A listing is an ordinary entry that holds an [Entries block](#the-entries-block) filled by a source.

- **The blog is a page** at the slug `blog`. With Page's empty prefix, it lives at `/en_US/blog`, next to posts under `/en_US/blog/…`. The usual uniqueness check covers both, and nothing ties a listing to a prefix ([URLs and SEO](urls-and-seo.md)).
- **A category's URL is its archive.** Its block tree holds an Entries block filled by In this category. The `blog_category_archive` starting point puts that block there, and the create dialog preselects it. Editors can add an intro or a hero around it, or remove it. The admin warns when the tree of a category with its own URL holds no Entries block, wherever it would be and however it's filled ([Admin](admin.md#problems)). A category created from Blank gets the warning too, as its archive lists nothing.
- **Listings see only what shoppers may see.** The entry sources build on the visible-entries query ([Sources](sources.md#visible-queries)), so they filter in SQL before paginating.

## The Entries block

One generic block, code `entries`, lists entries of any collection, mirroring the Products block ([Blocks](blocks.md)).

| Field | Field type | Translatable |
| --- | --- | --- |
| `entries` | Entries, `limit: 9` | no |
| `display` | Choice of the displays the listed entries' cards support | no |
| `paginate` | Toggle, off by default | no |

- **Filling it.** Hand-picked, or one of the entry sources: From a collection, In this category or Related ([Sources](sources.md#entry-sources)).
- **Displays.** The block offers the displays that the listed content type has cards for: `grid` and `carousel` for blog posts, and `accordion` for FAQ items. A source says which content type it lists from its parameters and the host entry's type ([Extending](extending.md#sources)), and all three entry sources do. A hand-picked list offers the displays that every picked entry's content type has, and one with nothing picked, or a source that can't tell, every display any content type has cards for. A display's choice label is `setono_sylius_cms.choice.entries_display.<display>`, or the display itself without one. The carousel display uses the shared carousel script ([Blocks](blocks.md#carousel)), showing 3 per view.
- **Each entry through its own card.** An entry whose content type has no card for the block's display is left out, and a block with no card to show prints nothing.
- **Markup.** `cms-entries cms-entries--<display>`, with a `cms-entries__item` around each card, and `<nav class="cms-pagination">` for the page links after them, named "Pages" to screen readers and left out when the list fits on one page. Inside the `nav`: a `cms-pagination__list` (`ol`) of `cms-pagination__item`s, each holding a `cms-pagination__link` (`a`). The current page's link has `cms-pagination__link--current` and `aria-current="page"`. `cms-pagination__previous` and `cms-pagination__next` are the links before and after the list (`rel="prev"` and `rel="next"`), left out on the first and last page. A gap in the numbers is a `cms-pagination__gap` item (an `li` holding `…`, hidden from screen readers). The list links the first and the last page and the two pages on each side of the current one, and a gap that would hide only one page links that page instead. The links are in `@SetonoSyliusCMSPlugin/block/_pagination.html.twig`, which themes override like any template.

### Cards

A [card](../../GLOSSARY.md#entries) is a Twig template a content type declares for one display: `#[ContentType(cards: [new Card('grid', '…')])]` ([Extending](extending.md#content-types)). Each listed entry renders through its own content type's card for the chosen display. A card template gets `entry` and `display`, and themes override it like any template ([Twig](extending.md#twig)).

Shipped cards:

| Template | Shows |
| --- | --- |
| `@SetonoSyliusCMSPlugin/card/blog_post/grid.html.twig` | the featured image, the date, the title and the excerpt, linking to the post |
| `@SetonoSyliusCMSPlugin/card/blog_post/carousel.html.twig` | the same, sized for a carousel slide |
| `@SetonoSyliusCMSPlugin/card/faq_item/accordion.html.twig` | a `<details>` with the question as its summary and the answer as its content, using the Accordion item's classes |

The blog post cards' markup: an `article.cms-blog-post-card.cms-blog-post-card--<display>` holding `cms-blog-post-card__image` (the `<picture>`), `cms-blog-post-card__date` (a `<time>`), `cms-blog-post-card__title` (an `h3`) with the `cms-blog-post-card__link` to the post, stretched over the whole card, and `cms-blog-post-card__excerpt`. Each part is left out when the post has none. Templates render a card with `cms_card(entry, display)` ([Twig](extending.md#twig)).

### Pagination

The Entries block can paginate, using numbered `?page=N` links that crawlers follow ([ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md)).

- **What paginates.** Turning on `paginate` needs a source, and publish refuses it on a hand-picked list. The page size is the list's `$limit`.
- **One per entry.** Only an entry with its own URL can paginate, through one Entries block in its own block tree. Snippets can't, because a snippet in a slot on a taxon page would clash with Sylius's own `?page=N`. Publish refuses a second paginated Entries block, and a paginated one in a snippet, at its `paginate` field. A paginated Entries block in a snippet that a Snippet block shows doesn't paginate. Should older or imported data hold a second paginated Entries block, only the first in tree order is counted before rendering, and the second shows its first items on a page its list doesn't have.
- **Counted before rendering.** The pass before rendering finds the paginated block, checks its visibility conditions and counts its source, so a page past the end returns 404 before any output. A block that throws while rendering is only dropped and logged, so a 404 from inside the block wouldn't work ([Rendering](rendering.md)).
- **Page bounds.**
  - Page 1 always exists, even when the list is empty.
  - `page` must be a whole number of 1 or more, written without a sign or leading zeros. Anything else, or a page past the last one, returns 404, on any entry shown at its own URL or as a homepage.
  - `?page=1` redirects with a `301` to the URL without it, keeping the rest of the query string.
  - If visibility conditions hide the block from the shopper, the entry has only page 1.
- **Canonical.** Page 2 onwards has a canonical of its own URL, including `?page=N`. That's the one exception to the canonical dropping the query string ([URLs and SEO](urls-and-seo.md#head-tags)). Page 1's canonical has no query string.

## Shipped layouts, starting points and presets

All text in the shipped presets is translation keys ([Layouts and presets](layouts-and-presets.md#code-presets)).

**Layouts**, one per content type with its own URL, each its type's default ([Layouts and presets](layouts-and-presets.md#shipped-layouts)):

- **Blog post: Article** (`article`). Contained width, showing the title, the date, links to its categories and the featured image above the tree.
- **Blog category: Standard** (`standard`). The title and the description above the tree.

**Starting points:**

| Code | For | Holds |
| --- | --- | --- |
| `blog_page` | Page | a Heading, and a paginated Entries grid from From a collection (`blog_post`, `$limit: 9`) |
| `faq_page` | Page | a Heading, and an Entries accordion from From a collection (`faq_item`, `$limit: 50`) |
| `blog_category_archive` | Blog category, the default | a paginated Entries grid from In this category (`$limit: 9`) |

Blog post has no starting point, so a new post starts from Blank.

- **The listings' page size.** The blog and each category's archive show 9 posts to a page, a grid of 3 by 3. The FAQ page doesn't paginate, so it shows up to 50 questions, which is every question most FAQs have, and an editor raises it for more.
- **Their layouts.** The Page starting points use the `standard` layout and show the title, with the Heading below it as the list's heading. The category archive uses Blog category's `standard` layout, which shows the category's title and description above the list.

**Block presets:**

| Code | Holds | Offered |
| --- | --- | --- |
| `latest_posts` | an Entries grid of the 3 newest blog posts, from From a collection (`blog_post`, `$limit: 3`) | anywhere |
| `related_posts` | an Entries grid of 3 from Related | only on blog posts |

The FAQ block preset, an Accordion of three items, is still the way to make a one-off FAQ inside a page ([Layouts and presets](layouts-and-presets.md#shipped-code-presets)).

## In the admin

The Blog and the FAQ each get an item in the Content hub, listing their entries and their categories ([Admin](admin.md#entry-lists)).
