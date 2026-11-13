# Categories are entries, and collections are listed by pages

A collection that groups its entries declares a category content type of its own, such as Blog category for the blog. Categories are entries, so like any other entry they get what their content type gives them. Blog category has translations, slugs, SEO fields, channels, an audience, drafts and a block tree. FAQ category has only a title per translation, because FAQ categories only group items and have no page of their own. An entry refers to its categories by UUID, as ADR 0002 requires. Publishing also writes those UUIDs to an indexed table of (entry, field, UUID), so a listing can filter by category and paginate in SQL on MySQL and PostgreSQL.

No collection has listing routes in code. A listing is an ordinary entry holding an Entries block filled by a source: the blog is a Page at the slug `blog`, and a category with its own URL, such as a Blog category, uses it as its archive, because its block tree holds an Entries block filled by "In this category". Each listed entry renders through a card that its content type declares.

We chose this because merchants need to add, rename and translate categories, and want to put a hero or featured posts around a list. Entries already give categories everything they need, and Craft 5 replaced its own categories with entries for the same reason. Pages already give listings layouts, SEO, translations and channels, so a listing needs nothing new beyond the Entries block.

## Considered Options

- **Sylius taxons as categories.** There'd be no new concept, but blog categories would mix into the catalogue's taxon menus and filters, and taxon URLs already show products.
- **A choice field declared in code.** It's the simplest option, but merchants couldn't add categories, and categories would have no URL or SEO.
- **Free-text tags.** They're easy to add, but get no translations or URLs, and typos split a group in two.
- **Listing routes in code**, such as `/blog` and its category archives, with Twig templates. That's predictable for agencies, but editors couldn't shape these pages, and their SEO and translations would need handling of their own.
- **A Doctrine many-to-many association for categories.** The ORM would handle the joins and deleting a category would clean up its links, but it breaks ADR 0002, and transfer would have to map IDs to UUIDs.
- **Category UUIDs in a JSON column only.** No extra table, but filtering needs JSON functions that differ between MySQL and PostgreSQL, with weak indexing.

## Consequences

- A deleted category leaves dangling UUIDs on its entries, which rendering skips like any dangling reference.
- The reference table mirrors published content only. A draft's categories live in the draft JSON until it's published.
- An entry with its own URL can hold one paginated Entries block in its own block tree. Snippets can't, because a snippet in a slot on a taxon page would clash with Sylius's own `?page=N`. The block pages in SQL through its source (ADR 0022). Its pages are `?page=N` links, and a page past the end returns 404. The source is counted before the entry renders, so the 404 comes before any output, since a block that throws while rendering is only dropped and logged. If visibility conditions hide the block from the shopper, the entry has only page 1. Page 1 always exists, even when the list is empty. Page 2 onwards has a canonical of its own URL, including `?page=N`, which is the one exception to dropping the query string from the canonical (ADR 0015).
- A listing page and the entries it lists share the URL space: the Page at `blog` and posts under `blog/…`. The usual uniqueness check covers them, and nothing ties the listing to the prefix.
- A category's archive works only while its tree holds the Entries block. Blog category's default starting point puts it there, and editors can remove it.
