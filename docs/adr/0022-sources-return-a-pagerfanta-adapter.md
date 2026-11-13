# Sources return a Pagerfanta adapter

A source's `resolve(params, context)` returns a Pagerfanta `AdapterInterface`, which gives a count and a slice by offset and length, and runs neither until asked. It takes no limit. The block holding the list decides how many items show. A list that doesn't paginate takes the first slice and never counts. The paginated Entries block wraps the adapter in a `Pagerfanta` for its `?page=N` links. Every source pages, whether it lists entries, products or taxons. A source built on Doctrine returns a `QueryAdapter`, so paging, counting and visibility filtering all happen in SQL. The plugin provides one query each for the entries, products and taxons a shopper may see. Shipped sources build on them, and agency sources should too.

We chose this because the paginated Entries block needs a count and an offset (ADR 0019), and a later API listing needs the same. Paging through the source is the only way to page a listing in SQL with correct page sizes and counts. Pagerfanta's adapter is exactly a count and a slice, and it ships adapters for Doctrine queries and arrays, so most sources write no paging code. Sylius already depends on it: 1.14 requires `^3` and 2.x requires `^4`, and both declare the same two methods, so the port stays cheap. One contract for every kind of source keeps to ADR 0020: a developer who has written one extension point can write the rest.

## Considered Options

- **Returning a Doctrine `QueryBuilder`.** API Platform's query extensions could act on it directly, but every source would have to be one ORM query. That rules out sources backed by analytics or an external service.
- **`resolve(params, context, offset, limit)` returning items and a total.** It has no dependency, but every source writes its own count, and every call pays for it, even a block that shows three items.
- **Our own count-and-slice interface.** It's Pagerfanta's adapter without the dependency, but we'd write our own Doctrine and array adapters.
- **Paging only entry sources**, while product and taxon sources keep a limit. That makes two contracts, and an API that pages products later would need another change.
- **Enforcing visibility** by passing the visible-entries query into entry sources, which narrow it and return it. Nothing could leak, but entry sources would get a second, Doctrine-only signature. A source is agency code, and agency code could just as well query anything from a block template.

## Consequences

- This replaces the `resolve(params, context, limit)` that was settled along with ADR 0020. The list field a source fills sets how many items show, so "From a collection" has no limit of its own.
- Every source returns a stable order that ends in a unique tie-breaker, such as the ID, so pages never overlap or skip items.
- In v2.0 only the Entries block paginates. The Products and Taxons blocks show the first N, and a later API can page them without a contract change.
- The visible-entries query covers whether an entry is published and enabled, its date window, its channels, a translation in the locale that isn't held back, and its audience (ADR 0009, ADR 0010). It reads the shopper, channel, locale and time from the render context, so the preview's forced Shown to and Date apply to listings too. The visible-products query mirrors Sylius's own shop listing: enabled, in the channel and translated into the locale. Visible taxons are the enabled ones. The plugin provides these queries but doesn't enforce them.
- Hand-picked lists filter their picks through the same visible queries and keep the editor's order, so their counts are right too.
- Bestsellers is swapped at its calculation, not its lookup. A calculator yields a score for each channel and product code, and the console command stores the scores in the plugin's table. The Bestsellers source joins visible products against that table, with its taxon filter and its order in SQL. A project with analytics data swaps the calculator. This replaces `BestsellersProviderInterface::getProductCodes(channel, ?taxon, limit)`.
- Pagerfanta's adapter interface is part of the plugin's public contract. Sylius 2.x moves it from `^3` to `^4`, and the methods stay the same.
