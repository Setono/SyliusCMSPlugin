# Entry URLs are matched last, by a route that looks them up

An entry with its own URL is served at `/{locale}/{prefix}/{slug}`. Each content type's prefix is a setting, and Page's is empty by default, so pages live at the root of the shop, such as `/en_US/about-us`. The plugin ships a route loader that the app imports under its shop prefix. The loader gives the entry route a priority of -1000, so it is matched after every other route, whatever order the routing files load in. The route's condition looks the URL up by the channel, the locale taken from the URL and the slug, including an entry's old slugs, which redirect to its current one. Saving or publishing an entry rejects a URL that an existing route already matches. A console command finds entries that a route added later now overrides.

A URL is unique per channel and locale, not per locale as Sylius product slugs are. Two entries with no channel in common can therefore share a URL, so a store with a channel per brand can give each brand its own About page, as Shopware and Magento allow per store view.

We chose this because merchants want clean URLs. The [research for this decision](https://github.com/Setono/SyliusCMSPlugin/blob/research/root-level-routing/docs/research/root-level-routing.md) found that root-level URLs failed in other Sylius CMS plugins for three avoidable reasons. They relied on the order routing files load in, their route conditions read Sylius's locale context before the locale was set, and nothing checked that a slug was free.

## Considered Options

- **A fixed prefix**, such as `/{locale}/pages/{slug}`, as BitBag and the Sylius CmsPlugin do. It can't collide with other routes, but gives URLs merchants don't want. Projects can still choose it by setting a prefix.
- **A chained dynamic router** (Symfony CMF), as Happy CMS and Sulu use. It gives the same result, but replaces the app's `router` service.
- **Looking entries up in a request listener before routing.** Entries could then override Sylius's own URLs.
- **Looking entries up when the router finds no route.** That happens outside the firewall, so signed-in customers would appear signed out.
- **URLs unique per locale, like products.** Every difference between channels would then have to go through block conditions in one entry.

## Consequences

- A route added after an entry was published silently wins over the entry. The console command catches this at deploy, but can't prevent it.
- Every URL that no other route matches costs an indexed lookup.
- The route condition runs before the firewall, so it only checks that the URL exists. The controller checks the audience.
- Editors can't use slugs that Sylius or the app already route, such as `cart`, `login` or `products/…`.
- Uniqueness per channel can't be a database index, because an entry's channels are a many-to-many relation. It's validated whenever an entry's slug or channels change, and again on publish.
- Changing a prefix moves every URL that uses it, and the plugin doesn't redirect the old URLs.
