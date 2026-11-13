# Rendering

How the shop renders slots, entries and block trees, what happens when something fails, why v2.0 caches nothing, and the profiler panel.

- **Terms:** [content structure](../../GLOSSARY.md#content-structure), [shop pages](../../GLOSSARY.md#shop-pages) (slot, placement, page scope, host-page context, binding), [visibility](../../GLOSSARY.md#visibility).
- **Decisions:** [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (storefront output renders on every request), [ADR 0004](../adr/0004-slots-have-platform-neutral-identifiers.md) (slot adapters and resolvers), [ADR 0005](../adr/0005-placements-take-effect-on-save.md) (placements), [ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md) (capped slots decided without rendering), [ADR 0015](../adr/0015-entry-urls-are-matched-last-by-a-route-that-looks-them-up.md) (URL lookup), [ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md) (stylesheet and script), [ADR 0019](../adr/0019-categories-are-entries-and-collections-are-listed-by-pages.md) and [ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md) (paginated listings), [ADR 0002](../adr/0002-references-by-code-and-uuid.md) (broken references).

## One read pipeline

Everything the shop renders goes through one pipeline in PHP, which Twig calls. It upgrades stored data, evaluates conditions, resolves locales and fallbacks, resolves bindings and hydrates values. None of that lives in `cms_field()` or in block templates. The builder's preview renders through the same pipeline, and so would a later API.

The pipeline reads the channel, locale, currency, customer and time only from the `RenderContext` it's given ([Extending](extending.md#rendering-services)). So do condition kinds, sources, bindable properties and link targets. They never read the session or the `sylius` Twig global. Preview mode and share links set Shown to and Date in the `RenderContext` and nowhere else, so every condition, source and visible query on the page follows them ([Targeting](targeting.md)).

## Rendering a slot

A slot renders where its template event fires, or where a template calls `cms_slot()` ([Slots](slots.md#rendering-a-slot)). It:

1. **Resolves its context.** It takes the keys it provides from what its template event passes, and runs the context resolver for any key the event doesn't pass ([Extending](extending.md#context-keys-and-resolvers)). A key that still can't be resolved, such as `product` in a slot on a page that isn't a product page, makes the slot print nothing.
2. **Takes the channel's placements for it.** All of the channel's placements load in one query when the first slot on the page renders, through `PlacementProviderInterface` ([Extending](extending.md#rendering-services)), and every later slot on the page reuses them.
3. **Filters them**, in editor order, keeping a placement when:
   - its page scope matches the host-page context ([Slots](slots.md#page-scopes))
   - its snippet is [visible](content-model.md#when-an-entry-shows): published, enabled, inside its date window, in the channel, translated and not held back, and in the shopper's audience
4. **Applies its cap**, keeping the first matching placements up to the cap. This needs no rendering: a snippet whose blocks are all hidden still wins and shows nothing ([Targeting](targeting.md)).
5. **Renders the winners in order**, each snippet's block tree with the slot's context.

A slot with nothing to show prints nothing, not even a wrapper. A placement whose slot or context key was removed from code is kept, not rendered, and flagged ([Slots](slots.md)).

## Rendering an entry at its own URL

1. **The URL lookup.** The route condition finds the entry by channel, locale and path, through `EntryUrlLookupInterface`, memoised within the request ([URLs and SEO](urls-and-seo.md#routing)). An old slug answers with a `301`.
2. **Visibility.** The controller checks that the entry is [visible](content-model.md#when-an-entry-shows), including its audience, and answers 404 otherwise.
3. **The pass before rendering** loads the block tree's references ([below](#rendering-a-block-tree)). It also finds the entry's paginated Entries block, if it has one ([Collections](collections.md)):
   - If the block's visibility conditions hide it from the shopper, the entry has only page 1.
   - Otherwise it counts the block's source and answers 404 for a `?page=N` past the last page, before any output. Page 1 always exists, even when the list is empty.
   - A `?page` that isn't a whole number of 1 or more answers 404, and `?page=1` redirects with a `301` to the URL without it.
4. **The layout.** The entry renders in its layout's template, resolved through Symfony bundle overrides and Sylius themes ([Extending](extending.md#twig)). An entry whose layout was removed from code renders in its content type's default layout, and the admin flags it.

The homepage renders the same way through the decorated homepage controller, without the URL lookup in step 1 ([URLs and SEO](urls-and-seo.md#homepage)).

## Rendering a block tree

The same steps render an entry's tree, a snippet in a slot, a referenced snippet and a single block in the builder's preview.

1. **Upgrade.** Blocks, the layout and the entry are brought up to date by the upgraders as they're read ([Content model](content-model.md#schema-changes)).
2. **Load references.** One pass over the tree collects every reference its fields hold, through the field type handlers, and loads every media item, entry, product and taxon with one query per kind, through `ReferenceLoaderInterface` ([Extending](extending.md#rendering-services)). Referenced snippets' trees are included in the same pass.
3. **Visibility conditions run before a block's template.** A block whose conditions don't match isn't rendered, and neither are its children. The template gets only the visible children, so a carousel's slide count and its dots are always right.
4. **Context flows down.** Each block template gets the host-page context of the entry or slot it renders in ([Extending](extending.md#block-templates)).
5. **Render.** Each block renders through its block type's renderer ([Extending](extending.md#block-renderers)).

- **Sources resolve lazily**, when a template first reads the list field. A list that doesn't paginate takes the first slice and never counts ([Sources](sources.md)).
- **A snippet that would render inside itself**, directly or through other snippets, is skipped the second time, and the admin flags the reference.
- **A block of a type the code no longer has** isn't rendered, and the admin flags it. A block of a disabled block type keeps rendering.
- **Broken references are skipped** ([ADR 0002](../adr/0002-references-by-code-and-uuid.md)): the field is empty, the list leaves the item out, a link isn't rendered, and a rich-text link renders as plain text. A reference to an entry that isn't visible is treated the same way.

## Hydration

The template gets the block type's hydrated object ([Extending](extending.md#block-templates)):

- **Values are hydrated lazily**, when a template reads them, by the field type's handler.
- **Translatable values** are read in the current locale and fall back through the [fallback chain](../../GLOSSARY.md#entries) ([Localization](localization.md)).
- **Bindings** resolve the bindable property against the host-page context, the channel or the customer. When the bound value is empty, the binding's fallback shows, and without a fallback the field is empty. Rich text binding tokens resolve the same way ([Slots](slots.md#bindings)).
- **Rich text** is rendered to HTML by `ueberdosis/tiptap-php`, with its links resolved through link targets.

## Failures

- **In production,** a block that throws while rendering drops out and is logged at `error`, with the entry's UUID, the block's key and its block type. Each block renders into its own string, so no half-printed markup is left behind, and the rest of the snippet and the page still show. A slot whose snippet throws is handled the same way.
- **In dev** (`kernel.debug` on), the exception is thrown.
- **In the builder's preview,** in dev and in production alike, the error shows on that block, with its message, and the rest of the preview still renders. Share links render like the shop.

A paginated Entries block's 404 doesn't rely on the block: its source is counted before anything renders ([above](#rendering-an-entry-at-its-own-url)).

## Preview mode and share links

The builder's preview and share links render through this pipeline, turned on by a token in the query string ([Builder](builder.md), [Publishing](publishing.md)):

- **Preview mode** (`_cms_preview`, a signed token) forces the channel, the locale, the sample object, Shown to and Date, and renders the builder's working copy of the edited entry. It brackets each block's output with HTML comment markers that carry its key, lets `cms_field()` add its in-place editing marker, and injects the bridge script.
- **A share link** (`_cms_share`) renders one entry's draft, or its published content when it has no draft, with the channel, locale, Shown to and Date fixed when the link was created. It has no markers and no bridge.
- **The edited entry ignores its own visibility**: an unpublished or disabled entry, or one outside its audience or date window, still renders. Its blocks still follow Shown to and Date. Everything else on the page, such as snippets in other slots, shows its published content and follows the same rules as for a shopper.
- **A snippet that loses its capped slot** still shows there in the preview, as if it won ([Targeting](targeting.md)).
- Both send `Cache-Control: no-store` and `X-Robots-Tag: noindex`. A share link also sends `Referrer-Policy: no-referrer`, so its token isn't passed on to other sites.
- **Read from the main request.** The token, and with it the preview or share state, is read from the main request (`RequestStack::getMainRequest()`), and so are context resolvers ([Extending](extending.md#context-keys-and-resolvers)). So slots that fire inside a Sylius sub-request, such as `taxon.header` and `taxon.sidebar`, preview, get patched in by the builder and show on share links like any other.
- **Templates can tell.** The plugin sets the request attribute `_cms_preview_mode` on the main request to `builder` or `share`, `RenderContext` carries the same value, and `cms_preview_mode()` returns it in Twig, `null` for shoppers ([Extending](extending.md#twig)). Themes and other plugins use it to leave out analytics, ad pixels, consent tools and A/B scripts, so the builder's reloads aren't counted as page views and share tokens don't reach analytics.

## No caching in v2.0

v2.0 caches nothing it renders ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)). Every request renders slots and entries from the database. There's no cache of rendered HTML, none of the inputs (placements, URL lookups, block trees), and no HTTP cache metadata for reverse proxies.

What stays in place for a cache added later:

- **Condition kinds declare what they read**, and kinds that read the time report their next change ([Extending](extending.md#condition-kinds)). A later HTML cache adds declarations that block types and sources opt into, and treats any that haven't declared themselves cacheable as uncacheable, so nothing written for v2.0 breaks.
- **Listings and sources report no next date boundary** in v2.0.
- **Change events** implement `StorefrontChangedInterface` and carry the affected UUIDs ([Extending](extending.md#events)). Nothing in v2.0 listens to them for caching.
- **Three services can be decorated:** `PlacementProviderInterface`, `EntryUrlLookupInterface` and `ReferenceLoaderInterface` ([Extending](extending.md#rendering-services)). A project that needs to cache placements, URL lookups or references decorates one with a tag-aware cache and invalidates it on `StorefrontChangedInterface` events. The plugin's docs include a cookbook page that shows how.

## Stylesheet and script

The shipped stylesheet and carousel script ([Blocks](blocks.md)) load through the shop layout's `sylius.shop.layout.stylesheets` and `sylius.shop.layout.javascripts` template events, on every shop page. `setono_sylius_cms.assets: false` turns that off for projects that bundle their own ([Extending](extending.md#configuration)).

## Profiler panel

A Symfony profiler panel, in dev, lists each slot rendered on the page, including slots rendered in sub-requests such as `taxon.header`, with:

- the context it resolved, and whether it came from the template event or a resolver
- every candidate placement, in editor order, with whether it showed and, if not, why: its page scope didn't match, its snippet isn't published or is disabled, outside its date window, not in the channel, not translated or held back, or outside the audience, or the slot's cap was already reached
- render time and any block that threw

## Kept for a later API

The model needs no change for a headless API ([API-readiness check](https://github.com/Setono/SyliusCMSPlugin/issues/209)). These build constraints keep the API an adapter:

- **One read pipeline**, [above](#one-read-pipeline), outside Twig.
- **One visibility query** for "entries this shopper may see", which sources, URL lookup and capped slots all use ([Sources](sources.md#visible-queries)).
- **A URL lookup service** that takes a channel, a locale and a path, and returns an entry, a redirect or nothing. The route condition and `setono:cms:check-routes` share it, and a later API would too.
- **A preview context service** set from the signed token on the main request, not from Twig.
- **Link targets return the reference too.** A `ResolvedLink` carries the target and its code or UUID beside the URL and name ([Extending](extending.md#link-targets)), so a later projection can give a headless frontend both.
- **Explicit context only**, as [above](#one-read-pipeline).
