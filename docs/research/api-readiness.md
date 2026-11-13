# API-readiness of the v2 model

Research for [API-readiness check (#209)](https://github.com/Setono/SyliusCMSPlugin/issues/209), part of the v2 map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186). Checked on 2026-11-13 against `GLOSSARY.md` and the 20 ADRs on the `2.x` branch, the resolutions of #192, #194, #197, #198, #202, #205 and #206, and the [Sylius 1.14 research](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md) (#190).

## Question

Can the settled model be exposed through API Platform later, on Sylius 1.14 and after the Sylius 2.x port, without remodeling? The hard cases:

1. single-table-inheritance entries with a JSON block tree
2. translatable values stored inside the tree
3. draft preview for a headless frontend
4. resolving a URL, or a redirect, by channel, locale and slug
5. collection listings by category, paginated, with the same filters listings use
6. audiences and visibility conditions on blocks

## Verdict

Yes. No hard case needs remodeling: each one works as modeled or needs only an adapter added together with the API. The calls that make headless APIs hard elsewhere were already made the API-friendly way:

- everything a listing filters on is a column or an indexed table (ADR 0001, 0009, 0010, 0019)
- references are codes and UUIDs (ADR 0002), and Sylius's own API uses `code` as its identifier
- slots have platform-neutral identifiers (ADR 0004)
- a draft can already be built into an entry that Doctrine doesn't manage (ADR 0009)

One extension-point contract does need changing now. The source contract settled in #206, `resolve(params, context, limit)`, can't page. The Entries block's `?page=N` pagination (ADR 0019) already needs paging, and a paginated API listing will need it later.

## Versions checked

| | Sylius 1.14 | Sylius 2.x |
|---|---|---|
| API Platform | `api-platform/core ^2.7.10` ([#190](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md)); latest 2.7 release is v2.7.18 | `api-platform/symfony ^4.3.17 \|\| ^5.0` in Sylius 2.3.2 ([composer.json](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json#L34-L36)); checked v4.3.21, plus v5.0.7 where noted |
| Metadata style Sylius uses | Legacy XML (`collectionOperations`/`itemOperations`) and legacy `ApiPlatform\Core\…` extension interfaces ([example](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Doctrine/QueryCollectionExtension/ProductsByChannelAndLocaleCodeExtension.php#L16)). Sylius doesn't set `metadata_backward_compatibility_layer` ([config.yaml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/app/config.yaml#L50)), so it stays at 2.7's default `true` ([upgrade guide](https://github.com/api-platform/docs/blob/2.7/core/upgrade-guide.md#L161-L184)) | `ApiPlatform\Metadata\…` operations in XML ([shop Product.xml](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Resources/config/api_platform/resources/shop/Product.xml)), state providers and processors |

## Results at a glance

| Hard case | Result | What it takes |
|---|---|---|
| 1. STI entries with a JSON block tree | Works as modeled, plus an adapter | One API resource per content type, and a read projection of the tree |
| 2. Translatable values in the tree | Works with an adapter | The projection collapses each locale map through the fallback chain |
| 3. Draft preview for a headless frontend | Works with an adapter | A provider that takes the preview or share-link token and builds the entry from the draft JSON; a builder bridge for a headless iframe |
| 4. URL or redirect by channel, locale and slug | Works with an adapter | A route resource backed by the same lookup as the entry route's condition |
| 5. Listings by category, paginated | Data works as modeled. **The source contract needs a change now** | Doctrine provider, query extensions and a category filter. Sources must page |
| 6. Audiences and block conditions | Audience works as modeled. Conditions work with an adapter | A query extension reads the API user's group. The projection evaluates conditions on the server, with host-page context passed as codes and UUIDs |

## How Sylius exposes its own catalogue

This is the baseline a CMS API should match, so the same frontend can call both.

- **Channel.** It comes from `ChannelContextInterface`, which resolves by hostname ([HostnameBasedRequestResolver](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Component/Channel/Context/RequestBased/HostnameBasedRequestResolver.php)). `ChannelContextBuilder` puts it in the serializer context ([1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/SerializerContextBuilder/ChannelContextBuilder.php#L35)).
- **Locale.** API paths have no `/{_locale}`, so the locale comes from `Accept-Language` through `RequestHeaderBasedLocaleContext` ([1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/LocaleBundle/Context/RequestHeaderBasedLocaleContext.php#L55)). `LocaleContextBuilder` puts it in the serializer context ([1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/SerializerContextBuilder/LocaleContextBuilder.php#L35)).
- **Translations.** Shop operations expose only the current locale's values through getters (`name`, `slug`, `description`). Admin operations expose `translations` as an object keyed by locale code ([1.14 serialization](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/serialization/Product.xml#L59-L89), [1.14 resource](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/api_resources/Product.xml#L243-L260)).
- **Shop visibility is filtered in SQL.** `ProductsByChannelAndLocaleCodeExtension` inner-joins the translation in the current locale and adds `:channel MEMBER OF o.channels`. It skips this for admin API users ([1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Doctrine/QueryCollectionExtension/ProductsByChannelAndLocaleCodeExtension.php#L58-L64)).
- **Lookup by slug.** `GET /api/v2/shop/products-by-slug/{slug}` resolves by channel, locale and slug, then answers with a 301 to the product's IRI ([1.14 operation](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/api_resources/Product.xml#L158-L161), [1.14 action](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Controller/GetProductBySlugAction.php#L38-L59), [2.3 operation](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Resources/config/api_platform/resources/shop/Product.xml#L58-L66)). Sylius 2.3 adds `taxons-by-slug/{slug}` with the requirement `.+`, so slugs with slashes match ([2.3 Taxon.xml](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Resources/config/api_platform/resources/shop/Taxon.xml#L92-L103), [action](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Controller/GetTaxonBySlugAction.php)).
- **Identifiers.** Products are identified by `code`, and `id` is marked not to be an identifier ([1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/api_resources/Product.xml#L239-L240)). These are the codes ADR 0002 stores, so a product in a block tree maps straight onto `/api/v2/shop/products/{code}`.
- **Model overrides.** Resources are declared on `%sylius.model.product.class%`, so the API exposes a project's own subclass ([2.3](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Resources/config/api_platform/resources/shop/Product.xml#L19)).

## 1. STI entries with a JSON block tree: works as modeled, and the tree needs a read projection

**Inheritance.** API Platform resolves every object to its most specific resource class. `ResourceClassResolver::getResourceClass()` walks all resource classes and keeps the most specific one the object is an instance of. The logic is the same in [v2.7.18](https://github.com/api-platform/core/blob/v2.7.18/src/Api/ResourceClassResolver.php#L37-L87), [v4.3.21](https://github.com/api-platform/core/blob/v4.3.21/src/Metadata/ResourceClassResolver.php#L39-L89) and v5.0.7. In 2.7, the legacy `ApiPlatform\Core\Api\ResourceClassResolver` is an alias of it. So when each content type is declared as an API resource, a result that mixes content types serializes each entry with its own IRI, type and properties. That covers a URL lookup, a referenced entry and a slot's snippets.

API Platform doesn't support polymorphism on a single parent resource. The properties of a subclass that isn't a resource are invisible, and OpenAPI gets no `discriminator`/`oneOf` ([api-platform/core#6915](https://github.com/api-platform/core/issues/6915), open). Invalidating the HTTP cache of parent collections under STI isn't handled either ([#7034](https://github.com/api-platform/core/issues/7034), open). So the API should declare one resource per content type, not one polymorphic `Entry` resource. The model already gives each content type what that needs:

- its own class, which is a Sylius resource
- a code that never changes and is also its discriminator value (ADR 0020)
- a model override exposed by declaring the resource on the class parameter, as Sylius does

Collections per content type then come for free. The Doctrine provider queries the subclass, and Doctrine adds the discriminator condition. UUIDs work as the API identifier on a Doctrine entity that also has an integer `id` ([identifiers, 4.3](https://github.com/api-platform/docs/blob/4.3/core/identifiers.md#L243-L275)).

**The block tree.** Shop operations must not expose the stored JSON as it is. The raw tree would leak or misstate four things:

- every locale's values, with their translation metadata (source locale and fingerprint, ADR 0007)
- blocks that visibility conditions hide, such as wholesale-only content or content whose date window hasn't started
- block-type versions that haven't been upgraded yet
- references as bare codes and UUIDs

A shop operation needs a delivery tree. It's upgraded (ADR 0020 upgraders), blocks whose conditions fail are removed, each locale map is collapsed, and references are resolved to IRIs, shop paths and media URLs. Rich text stays ProseMirror JSON, optionally with HTML from tiptap-php. Each API Platform version has a way to produce it:

- **4.x:** a DTO resource mapped to the entity through `stateOptions: new Options(entityClass: …)` ([dto, 4.3](https://github.com/api-platform/docs/blob/4.3/core/dto.md#L24-L35)), or a normalizer for the tree property
- **2.7:** an output DTO ([dto, 2.7](https://github.com/api-platform/docs/blob/2.7/core/dto.md#L86))

The block classes of ADR 0020 already have the right shape for a delivery block: typed properties, hydrated for Twig.

A later admin API can expose the stored tree unchanged, as Sylius exposes `translations` to admins. Its writes have to go to the draft, not to the rows (ADR 0009), so they need a custom processor. The builder's JSON controllers (ADR 0008) already own that merge logic.

## 2. Translatable values inside the tree: works with an adapter

Sylius's own API serves shoppers one locale and admins every locale. The model maps onto that split:

- **Shop.** The projection collapses each `{locale: value}` map to the request's locale through the fallback chain. ADR 0007 makes fallback per field, ending at the store's fallback locale. Sylius's own translation fallback, which only applies when a whole translation is missing, must not be used for tree values. The projection calls the same fallback service that `cms_field()` uses (#206).
- **Admin.** The maps are exposed as they are.

Entry-level translatable fields sit on translation subclasses that mirror the STI, as columns (ADR 0007). That's the same shape as Sylius product translations, so Sylius's pattern applies directly: current-locale getters for the shop, and `translations` keyed by locale for the admin.

Whether an entry shows in a locale (a translation exists and isn't held back) is decided on the published translation rows. So it's an SQL condition, like Sylius's inner join on the translation in the current locale.

## 3. Draft preview for a headless frontend: works with an adapter

What the model already has:

- Published content stays in the rows, and a draft is one JSON document per entry (ADR 0009).
- A share link shows a draft in one channel and locale, with "Shown to" and "Date" fixed when it's created (#197, #198).
- ADR 0009 already says a share link renders a draft "by building an entry from its JSON that Doctrine doesn't manage".

**What API Platform supports.** A custom state provider may return any object, whether Doctrine manages it or not. An object Doctrine doesn't manage flags its identifier with `#[ApiProperty(identifier: true)]` ([state providers, 4.3](https://github.com/api-platform/docs/blob/4.3/core/state-providers.md#L1-L35)), and entries have a UUID for that. So a preview operation can serve the draft. It's either its own operation keyed by token or a header that the entry operations honour. It does four things:

1. load the draft
2. run the upgraders
3. build the entry Doctrine doesn't manage
4. send it through the same projection, with `Cache-Control: private, no-store` and noindex

**A page is several API calls.** Twig preview mode is request-scoped and forces channel, locale, Shown to and Date for every condition on the page, including snippets in slots (#198). The headless equivalent: the frontend sends the token on every call, such as the entry and its slots, and the API turns it into the same preview context.

**Find the draft by entry, not by URL.** A draft's slug can differ from the published one, and URL lookup only sees published rows (ADR 0009). The token must therefore name the entry. Share links already do.

**Live preview in the builder.** ADR 0008's preview is a same-origin iframe of the Twig shop, with HTML comment markers that carry block keys and a `postMessage` bridge. For a headless store, that iframe would be the frontend:

- The frontend is cross-origin, where `postMessage` still works with an origin check ([MDN](https://developer.mozilla.org/en-US/docs/Web/API/Window/postMessage)).
- The markers become attributes the frontend prints from block keys.
- The unsaved working copy reaches the frontend in one of two ways: autosave followed by a refetch, or the builder posting a projected working copy.

This is all builder adapter work. The model already holds the data it needs: stable block keys, a working copy in the stored format, and a signed token that carries channel, locale, Shown to and Date.

## 4. URL or redirect by channel, locale and slug: works with an adapter

**What the model decides** (ADR 0015, #202):

- An entry's URL is `/{locale}/{prefix}/{slug}`, unique per channel and locale.
- A URL is looked up by the channel (from the host), the locale (from the URL) and the slug, old slugs included.
- An old slug answers a 301. An unavailable entry answers 404.
- A page can be a channel's homepage, stored by channel code. The homepage's own URL gets a 301 to `/{locale}/`.

**The Sylius precedent fits.** Products-by-slug resolves by the channel context, the locale context and the slug. Taxons-by-slug in 2.3 accepts slashes with `.+`. A CMS route operation can work the same way, either as a path such as `GET /api/v2/shop/cms-routes/{path}` with `.+` or with a `?path=` parameter. It takes the channel from Sylius's channel context, like every Sylius shop operation. It takes the locale from the path's first segment, falling back to `Accept-Language`.

**One difference from Sylius.** An HTTP 301 to an API IRI can't say "this URL moved, send the shopper to `/en_US/new-slug`". So the operation should answer 200 with a small route resource:

- `{entry: <IRI>}` for a current URL
- `{redirect: "/en_US/new-slug", status: 301}` for an old slug or the homepage's own URL

The frontend then issues its own redirect. Unavailable entries still answer 404, the same as a URL that never existed.

In the shop, the route condition runs before the firewall and only checks that the URL exists, and the controller checks the audience (ADR 0015). In the API, the provider does both, with the shop user from the JWT.

## 5. Listings by category, paginated: the data works as modeled, and the source contract needs a change now

**Every listing filter is SQL** (ADR 0009, 0010, 0019, #205):

- channels (a many-to-many)
- a translation in the locale that isn't held back
- `enabled`, and whether the entry has been published
- the date window columns
- the audience, as a mode column plus a table of group codes
- the category, through the indexed (entry, field, UUID) table
- each content type's order, declared in code

**API Platform's Doctrine ORM provider handles this natively:**

- Query collection extensions add the filters every shop query needs ([extensions, 4.3](https://github.com/api-platform/docs/blob/4.3/core/extensions.md#L1-L20)). Sylius does this for channel and locale.
- A custom filter joins the reference table for `?category=<uuid>`.
- Built-in pagination returns a Hydra collection with `totalItems` and links to the other pages. The page parameter defaults to `page` ([pagination, 4.3](https://github.com/api-platform/docs/blob/4.3/core/pagination.md#L1-L46)), the same `?page=N` the Entries block uses.

The 404 past the last page (ADR 0019) is a shop rule, and an API operation can follow it or not.

**The gap.** A headless frontend that renders the blog Page receives an Entries block whose source settings say what to list. To list exactly what the Entries block lists, the API should run that block's source, as Twig does. But #206 settled the source contract as `resolve(params, context, limit)`. It takes a limit, but no page or offset, and returns no total. So it can't serve the Entries block's own `?page=N` pagination (ADR 0019, #205), and later it can't serve an API page either.

API Platform also can't fill that gap for us. Custom providers paginate only when they return a `PaginatorInterface` or `PartialPaginatorInterface`, such as `ArrayPaginator` or `TraversablePaginator` ([4.3](https://github.com/api-platform/docs/blob/4.3/core/pagination.md#L580-L590), [2.7](https://github.com/api-platform/docs/blob/2.7/core/pagination.md#L515-L523)). Doctrine extensions don't run inside custom providers unless the provider applies them: "If you use custom state providers, they must support extensions and be aware of active extensions to work properly" ([extensions, 4.3](https://github.com/api-platform/docs/blob/4.3/core/extensions.md#L1-L20)).

**Change needed now.** Sources should return something the caller pages, not a list already cut to `limit`. That can be a Doctrine query, or a paginator with a total count. Entry sources should build their query on one plugin-provided query of the entries this shopper may see, so an agency's source can't forget the audience or the date window. Hand-picked wraps its list in an array paginator. Once agencies write sources against the released contract, changing it breaks them, so it has to be settled before the build.

With that change, an API can offer two shapes that share one filter:

- `GET /api/v2/shop/cms/blog-posts?category=<uuid>&page=2`, through the Doctrine provider, the extensions and the category filter
- `GET /api/v2/shop/cms-entries/{uuid}/blocks/{key}/items?page=2`, which runs the block's source and returns its paginator

## 6. Audiences and block conditions: audience works as modeled, conditions work with an adapter

**Audience.** It's a mode column plus a table of group codes, filtered in SQL (ADR 0010). In the API, the shopper's group comes from the authenticated shop user, through Sylius's `UserContextInterface` with a JWT. A request without a token is a guest. A query extension reads the group the same way Sylius's extensions read `UserContextInterface` to skip filters for admins. An entry outside the audience then answers 404 on item operations and is left out of collections, matching "behaves like a disabled one".

**Visibility conditions on blocks.** They're stored in the tree and evaluated in PHP by `matches(params, context)`, against the context they're given (#206). They already follow the forced Shown to and Date in preview, so the API projection can run them unchanged.

The projection must drop failing blocks on the server, not ship the conditions to the client. Shipping them would leak content meant for other customer groups or later dates. The client would also have to reimplement every condition kind developers add. Two inputs must stay out of the client's control:

- The customer group comes only from authentication, never from a query parameter.
- Shown to and Date come only from a signed preview token.

**Host-page context.** Conditions on host-page context, page scopes and bindings all need it. In a headless store, the frontend owns the host page, so it passes the context as identifiers: product and taxon codes, and entry UUIDs (ADR 0002). For example: `GET /api/v2/shop/cms-slots/product.after_description?product=CODE`. The rest of the model supports this:

- Slots have platform-neutral identifiers (ADR 0004).
- Snippets and condition kinds declare the context keys they need (#193, #206), so the API can reject a call that's missing one.
- Context resolvers are registered per key (ADR 0020), so an API-side resolver per key can load the object from its identifier.
- Capped slots are decided without rendering (#198), and an API can decide them the same way.

**Caching.** Each condition kind declares what it reads, and the ones that read the clock report their next change (#198, #206). That's what an API needs to set `Vary` and `Cache-Control: max-age`. On 1.14 every shop response is private anyway (#190). Events that change shopper output carry the affected UUIDs (`StorefrontChangedInterface`, #206), and these map onto API Platform's IRI-based cache tags. Parent collections under STI need purging by hand ([#7034](https://github.com/api-platform/core/issues/7034)).

## The change the model needs now

1. **Sources must page (ADR 0020, with ADR 0019).** The source contract settled in #206, `resolve(params, context, limit)`, should return a result the caller pages: a query, or a paginator with a total. Entry sources should build on one plugin-provided query of the entries the shopper may see. The Entries block's `?page=N`, and later an API listing, then page in SQL through the same source.

## Build constraints that keep the API an adapter

These aren't model decisions, but they're cheap now and expensive to retrofit:

- **One read pipeline.** Upgrade the tree, evaluate conditions, resolve locales and fallback, resolve bindings and hydrate, all in a PHP service that Twig calls. None of it should live inside `cms_field()` or block templates.
- **One visibility query.** A single query builder for "entries this shopper may see" (channel, translation, held back, enabled, published, date window, audience). Sources, URL lookup, capped slots and later API extensions all use it.
- **A URL lookup service.** It takes (channel, locale, path) and returns an entry, a redirect target or nothing. The route condition, the API and `setono:cms:check-routes` share it.
- **A preview context service.** It holds the channel, locale, Shown to, Date and which entry's draft to use, scoped to the request and set from the signed token, not from Twig.
- **Link targets return the reference too.** They return the reference (type plus code or UUID) as well as the shop URL, so a projection can give a headless frontend both.
- **Explicit context only.** Condition kinds, sources and bindable properties read channel, locale, customer and clock only from the context they're given. They never read the session or the `sylius` Twig global.

## Version notes

- **Sylius 1.14.** An API on 1.14 would be written against API Platform 2.7 the way Sylius 1.14's own API is: legacy XML metadata and `ApiPlatform\Core\…` extension interfaces, with the backward-compatibility flag left at `true`. Setting the flag to `false` changes the interfaces of services that Sylius's own code depends on ([upgrade guide](https://github.com/api-platform/docs/blob/2.7/core/upgrade-guide.md#L161-L184)). The 2.x port would then rewrite that API for API Platform 4 or 5 (#190).
- **Sylius 2.x.** If an API comes, it's cheapest to build it in the 2.x port, on state providers, Doctrine extensions and DTO resources. The model is the same either way.
- **API Platform 5.0.** Sylius 2.3 allows it. `ResourceClassResolver` is unchanged in v5.0.7. The 5.0 docs weren't reviewed separately.

## Sources

**Sylius 1.14.21**

- [ApiBundle `config.yaml`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/app/config.yaml#L50)
- [`api_resources/Product.xml`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/api_resources/Product.xml)
- [`serialization/Product.xml`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/serialization/Product.xml#L59-L89)
- [`GetProductBySlugAction`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Controller/GetProductBySlugAction.php)
- [`ProductsByChannelAndLocaleCodeExtension`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Doctrine/QueryCollectionExtension/ProductsByChannelAndLocaleCodeExtension.php)
- [`ChannelContextBuilder`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/SerializerContextBuilder/ChannelContextBuilder.php)
- [`LocaleContextBuilder`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/SerializerContextBuilder/LocaleContextBuilder.php)
- [`RequestHeaderBasedLocaleContext`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/LocaleBundle/Context/RequestHeaderBasedLocaleContext.php)
- [`HostnameBasedRequestResolver`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Component/Channel/Context/RequestBased/HostnameBasedRequestResolver.php)

**Sylius 2.3.2**

- [`composer.json`](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json#L34-L36)
- [shop `Product.xml`](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Resources/config/api_platform/resources/shop/Product.xml)
- [shop `Taxon.xml`](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Resources/config/api_platform/resources/shop/Taxon.xml#L92-L103)
- [`GetTaxonBySlugAction`](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ApiBundle/Controller/GetTaxonBySlugAction.php)

**API Platform source**

- `ResourceClassResolver` in [v2.7.18](https://github.com/api-platform/core/blob/v2.7.18/src/Api/ResourceClassResolver.php), [v4.3.21](https://github.com/api-platform/core/blob/v4.3.21/src/Metadata/ResourceClassResolver.php) and [v5.0.7](https://github.com/api-platform/core/blob/v5.0.7/src/Metadata/ResourceClassResolver.php)
- Issues [#6915 (polymorphism)](https://github.com/api-platform/core/issues/6915) and [#7034 (STI cache purge)](https://github.com/api-platform/core/issues/7034)

**API Platform docs, 2.7**

- [upgrade guide](https://github.com/api-platform/docs/blob/2.7/core/upgrade-guide.md)
- [dto](https://github.com/api-platform/docs/blob/2.7/core/dto.md)
- [pagination](https://github.com/api-platform/docs/blob/2.7/core/pagination.md)

**API Platform docs, 4.3**

- [state providers](https://github.com/api-platform/docs/blob/4.3/core/state-providers.md)
- [dto](https://github.com/api-platform/docs/blob/4.3/core/dto.md)
- [pagination](https://github.com/api-platform/docs/blob/4.3/core/pagination.md)
- [extensions](https://github.com/api-platform/docs/blob/4.3/core/extensions.md)
- [identifiers](https://github.com/api-platform/docs/blob/4.3/core/identifiers.md)

**Other**

- [MDN: `Window.postMessage()`](https://developer.mozilla.org/en-US/docs/Web/API/Window/postMessage)
