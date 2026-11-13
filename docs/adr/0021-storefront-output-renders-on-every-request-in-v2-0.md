# Storefront output renders on every request in v2.0

v2.0 caches nothing the shop renders. Every request renders its slots and entries from the database: a channel's placements load in one query on the first slot, an entry's URL in one indexed lookup, and the references in a block tree with one query per kind. There's no cache of rendered HTML, no cache of these inputs, and no HTTP cache metadata (tags, `s-maxage`, `Vary` or ESI slots) for reverse proxies.

We chose this because Sylius 1.14 already renders its own shop pages on every request. The session makes every shop page private, so a proxy can't cache them without project work that 1.14 doesn't support. The costliest CMS content, product carousels, shows prices and stock that change without a CMS save, so an HTML cache would have to leave it out anyway. What's left is cheap to render, and no invalidation path can serve stale content after a publish if it was never built. The inputs are single indexed queries, and caching them would need a shared pool, such as Redis, on shops with several servers.

## Considered Options

- **A cache of each snippet's and entry's HTML, invalidated by tags.** It would be keyed by channel, locale, the host-page keys the content reads and the customer group if read, and expire at the next date boundary. This is what Drupal, Sulu, Shopware and Craft do. Product cards would have to be cut out as placeholders or tagged through Doctrine listeners on Sylius entities, and every change that takes effect on save would need a correct invalidation.
- **The same cache, off by default.** It has the same build and spec cost, and a project that never turns it on gets nothing from it.
- **Caching the inputs**, meaning placements, URL lookups and block trees, in a pool invalidated by tags. It saves about three queries a page. On shops with several servers, a server with its own cache would keep serving old placements after a save.
- **HTTP cache metadata for projects with a proxy.** It pays off only once a project makes its shop pages public.

## Consequences

- Change events still implement `StorefrontChangedInterface` and carry the affected UUIDs (ADR 0020). Nothing in v2.0 listens to them for caching, but projects can.
- Placements, URL lookups and reference loading each sit behind one service. A project that needs to cache them decorates that service and invalidates on those events, as a cookbook page shows.
- Condition kinds still declare what they read, and clock readers still report their next change (ADR 0020). A later HTML cache adds declarations that block types and sources opt into, and treats any that haven't declared themselves cacheable as uncacheable. Adding it therefore breaks no block type or source written for v2.0. Listings and sources report no next date boundary in v2.0.
- ADR 0013 says saving a media item has to expire cached output, but v2.0 caches nothing for it to expire. The `MediaItemSaved` and `MediaItemReplaced` events carry that duty to any cache added later.
- Customer bindings, such as "Hi {first name}", cost nothing and ship in v2.0.
- The builder's preview and share links send `Cache-Control: no-store` and `X-Robots-Tag: noindex`.
