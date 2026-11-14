# URLs and SEO

How an entry with its own URL is routed, how slugs and old slugs work, the homepage, and the SEO fields and head tags.

- **Terms:** [entries](../../GLOSSARY.md#entries): slug, page, homepage. [Publishing](../../GLOSSARY.md#publishing): share link.
- **Decisions:** [ADR 0015](../adr/0015-entry-urls-are-matched-last-by-a-route-that-looks-them-up.md) (entry URLs are matched last, by a route that looks them up), [ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md) (listings and their `?page=N` canonical), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (previews and share links aren't cached or indexed).

## URL shape

An entry with its own URL is served at:

```
/{locale}/{prefix}/{slug}
```

- **The prefix** belongs to the content type. It's declared in `#[ContentType(prefix: …)]` ([Extending](extending.md#content-types)), and a project changes it under `setono_sylius_cms.content_types.<code>.prefix` ([Extending](extending.md#configuration)).
- **An empty prefix** leaves out its segment, so URLs sit at the root. Page's prefix is `''`, so pages live at URLs such as `/en_US/about-us`. A project that prefers a prefix sets one, such as `pg`.
- **Shipped prefixes:** `page` is `''`, `blog_post` is `blog` and `blog_category` is `blog/category` ([Collections](collections.md)). FAQ items and FAQ categories, and snippets, have no URL of their own.
- **Changing a prefix moves every URL that uses it.** The plugin doesn't redirect the old URLs, and doesn't record them as old slugs.
- **Entries are flat.** There's no page tree in v2.0, no inherited path and no automatic breadcrumbs. A listing at `blog` and posts under `blog/…` share the URL space only by their slugs and prefixes, and nothing ties them together ([ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md)).

## Slugs

- **A slug is per translation**, and is drafted like the rest of the translation. A changed URL goes live on publish ([Publishing](publishing.md)).
- **Generated from the title** until an editor changes the slug. Once changed by hand, it no longer follows the title.
- **Sylius's slug rules.** Slugs are generated with Sylius's slug generator. Editors may type slashes, such as `about/team`. Renaming `about` doesn't move `about/team`.
- **A slug can't be empty, start or end with a slash, or contain two slashes in a row.**

### Unique per channel and locale

No two entries have the same URL in one channel and locale ([ADR 0015](../adr/0015-entry-urls-are-matched-last-by-a-route-that-looks-them-up.md)). The URL is the prefix plus the slug, so it covers entries of different content types too. Two entries with no channel in common can share a URL.

The check runs:

- **on save**, when a slug in the draft or the entry's channels change, against the other entries' published URLs and drafted slugs, as a warning in the builder, because a draft saves even when it's invalid
- **on publish**, against the published URLs, as an error that refuses the publish
- **when a scheduled publish runs**: a clash that appeared since scheduling stops it, and it's flagged as a failed scheduled publish ([Publishing](publishing.md))
- **on import**, where a clash is a conflict that stops the import ([Transfer](transfer.md))

Changing an entry's channels takes effect on save, so a channel change that would make a live URL clash is refused on save.

### Reserved URLs

Saving or publishing rejects a URL that an existing route already matches, such as `cart`, `login` or `products/…` ([ADR 0015](../adr/0015-entry-urls-are-matched-last-by-a-route-that-looks-them-up.md)). The plugin runs the candidate path through the router's matcher, in the entry's channels and locales, and rejects it when a route other than `setono_sylius_cms_shop_entry` matches. The error names the route.

This is the one exception to a draft saving even when it's invalid. When an autosave holds such a slug, the server saves every other change, keeps the slug's last saved value, and answers with the error, which the builder shows on the slug field.

## Routing

The plugin ships a route loader, which the app imports once under its shop prefix ([Extending](extending.md#routes)):

```yaml
setono_sylius_cms_shop:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/shop.php'
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```

- **One route,** `setono_sylius_cms_shop_entry`, with the path `/{path}` and the requirement `path: .+(?<!/)`.
- **Priority -1000.** The loader sets it, because YAML can't set route priority in Symfony 6.4. The route is matched after every other route, whatever order the routing files load in.
- **The route condition looks the URL up** through `EntryUrlLookupInterface` ([Extending](extending.md#rendering-services)) by:
  - the channel, from Sylius's channel context (`sylius.context.channel`), so a single-channel shop and the dev environment's fake channel resolve as they do everywhere else in the shop
  - the locale, taken from the URL's `_locale` parameter, never from Sylius's locale context, which isn't set yet when conditions run
  - the path, which is the prefix and the slug, including old slugs
- **The lookup is memoised** within a request, so the condition and the controller share one query.
- **A URL no entry claims** fails the condition, so the request falls through to Sylius's ordinary 404.
- **The condition runs before the firewall**, so it only checks that the URL exists in that channel and locale. The controller checks that the entry is [visible](content-model.md#when-an-entry-shows), including its audience, which needs the signed-in customer.
- **Generating a URL.** Templates use `cms_url(entry)` or a link ([Extending](extending.md#twig)), never the route name directly.

Every URL that no other route matches costs one indexed lookup.

### Routes added later

A route added after an entry was published silently wins over the entry. `setono:cms:check-routes` ([Extending](extending.md#console-commands)) runs every published URL, in every channel and locale it shows in, through the router. It lists each one another route now matches, with the channel, the locale, the URL and the matching route's name, and exits non-zero when it finds any. Run it at deploy and in CI.

The admin flags such entries as a warning, "URL taken by a Sylius route" ([Admin](admin.md#problems)).

## Old slugs

- **Kept per locale.** The plugin keeps every slug an entry has had in each locale. A publish that changes a slug records the old one.
- **They redirect.** A request to an old URL gets a `301` to the entry's current URL in that channel and locale.
- **Only while no live entry claims the URL.** An old slug never blocks a new entry: once another entry is published at that URL, that entry wins and the old slug stops redirecting.
- **An unavailable entry's old slugs answer 404**, like its current URL.
- **They're recorded in each environment.** Old slugs don't travel in a transfer. A target records its own when a publish there changes a slug ([Transfer](transfer.md)).
- The builder's URL & SEO tab and the entry's overview page list them ([Admin](admin.md#entry-overview-page)).

## Unavailable entries

Every entry that isn't [visible](content-model.md#when-an-entry-shows) answers `404`, the same as a URL that never existed. That covers:

- an entry that was never published, or is disabled
- an entry outside its date window
- an entry not in the current channel
- a missing or held-back translation in the current locale
- an audience that excludes the shopper, with no redirect to sign in

There's no `410` and no redirect to the homepage.

## Homepage

A page can be a channel's [homepage](../../GLOSSARY.md#entries).

- **"Homepage of"** is a list of channels in the page's settings. Each channel has at most one homepage, so choosing a page for a channel takes the channel off any other page.
- **Stored on the CMS side, by channel code**, so projects don't extend Sylius's Channel entity.
- **It takes effect on save**, like placements, and fires `EntrySettingsChanged` with `homepage` ([Extending](extending.md#events)). It travels in a transfer as an on-save setting ([Transfer](transfer.md)).

### Rendering the homepage

- **The plugin decorates Sylius's homepage controller**, `sylius.controller.shop.homepage`. On a channel with a homepage, `/{locale}/` renders that page in its layout.
- **If the shopper can't see the page**, Sylius's own homepage renders instead, so the homepage never answers 404.
- **The route doesn't change.** It stays `sylius_shop_homepage`, so every `path('sylius_shop_homepage')` in themes keeps working.
- **The page's own URL gets a `301` to `/{locale}/`** in every channel where it's the homepage. Links to the page point straight there, and `/{locale}/` is its canonical URL.
- **The builder previews it at `/{locale}/`.**
- **Sylius's homepage template doesn't render** on a channel with a CMS homepage, so placements in the `store_homepage` stock slot don't show there. The admin warns about such placements ([Admin](admin.md#problems)). The page's layout can have slots of its own ([Slots](slots.md)).

## SEO fields

Every content type with its own URL gets three SEO fields:

| Field | Translatable | Falls back to |
| --- | --- | --- |
| Meta title (`metaTitle`) | yes | the entry's title |
| Meta description (`metaDescription`) | yes | the field the content type names in `descriptionField`, such as a blog post's excerpt, and otherwise nothing |
| Hide from search engines (`noindex`) | no | off |

These fallbacks are separate from the fallback chain: each SEO field first falls back through the chain, and only when that leaves it empty does it use the field it names. There are no keywords, no canonical override and no social image. The fields sit on the builder's URL & SEO tab ([Builder](builder.md)).

## Head tags

An entry shown at its own URL, or as a homepage, prints these into the shop layout. `@SetonoSyliusCMSPlugin/layout/base.html.twig` fills the shop layout's `title` and `metatags` blocks with them, so every layout that extends it gets them ([Extending](extending.md#layout-templates)).

- **Title:** the meta title, or the title.
- **Meta description:** `<meta name="description">`, left out when empty.
- **Robots:** `<meta name="robots" content="noindex">` when "Hide from search engines" is on.
- **Canonical:** `<link rel="canonical">` with the entry's own absolute URL in the current channel and locale, without the query string. For a homepage it's `/{locale}/`. On page 2 onwards of a paginated Entries block, it's the entry's own URL with `?page=N`, the one exception to dropping the query string ([ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md)).
- **Hreflang:** a `<link rel="alternate" hreflang="…">` for each locale the entry is visible in for the current channel and shopper, with that locale's absolute URL, plus `x-default` pointing at the channel's default locale when the entry is visible there. Hreflang values use the locale code with a hyphen, such as `en-US`. Page 2 and later of a [paginated listing](collections.md#pagination) print no hreflang, because the other locales' page N needn't hold the same entries.

The CMS prints no Open Graph tags, so they never appear twice beside an SEO plugin's.

## Previews and share links

The builder's preview and share links render through the same routes, turned on by a token in the query string ([Builder](builder.md), [Publishing](publishing.md)). Both send `Cache-Control: no-store` and `X-Robots-Tag: noindex` ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)).

## Not in v2.0

- **An XML sitemap.** There's no sitemap of the CMS's own and no provider for `stefandoorn/sitemap-plugin`.
- **Structured data (JSON-LD).**
- **Open Graph.** It's left to SEO plugins such as `setono/sylius-seo-plugin`, which already print channel Open Graph tags on every page.
- **CMS-built error pages.** Error pages stay Sylius's own.
- **Manual redirects.** They stay with `setono/sylius-redirect-plugin`, which v2 doesn't depend on. The CMS only redirects an entry's old slugs.
