# How CMSs cache block-based and personalised output

Research for [#207](https://github.com/Setono/SyliusCMSPlugin/issues/207), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186). It feeds the decision ticket [#208 "Storefront rendering and caching"](https://github.com/Setono/SyliusCMSPlugin/issues/208).
Researched 2026-11-13. Sources are official docs and source code: Drupal's Cache API and render docs, Sulu `2.6` and `3.0` source, Shopware `trunk` source and developer docs, Craft `5.x` source and docs, Symfony 6.4 docs and source, the FOSHttpCache and FOSHttpCacheBundle docs, and Varnish's docs and `varnish-modules`.
Every claim links to the page or file that owns it. Terms follow v2's [`GLOSSARY.md`](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/GLOSSARY.md).

## Short answer

Every CMS surveyed does the same three things, under different names:

1. **It records what the output read.** Drupal calls these cache contexts. Sulu flags "influenced content" when a block reads the target group. Shopware hashes the rules that matched for the request.
2. **It tags output with what it shows.** Drupal uses tags like `node:5`, Sulu the UUIDs of referenced documents, Shopware entity tags, and Craft `element::<id>`. A save then invalidates the tags.
3. **It caps lifetime at the next time-based change.** Sulu's block schedules and Craft's expiry dates both set the cache duration to the soonest upcoming boundary.

Personalised output is kept in a cache only for **groups**, never for single users. Drupal varies by role set, Sulu by target group, Shopware by a hash of matched rules, and FOSHttpCache by a user-context hash. Per-user parts are cut out as placeholders, ESI fragments or JavaScript requests.

On Sylius 1.14 every shop page is private because of the session ([#190 findings §7](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md#7-http-caching)). A plugin can't change that on its own, so the realistic default is an **application-level cache**. The plugin can still emit HTTP cache metadata, and a project that runs Varnish or Symfony HttpCache can use it.

v2 has one advantage the others lack. Conditions, bindings and sources are declared in code and stored as data in the block tree, so what a snippet reads can be worked out from its published tree before rendering. Drupal has to discover this during rendering and needs cache redirects for it.

---

## 1. What varies and what expires in v2

The settled design fixes the cache dimensions. This is the checklist that every option in §8 has to satisfy.

**What output varies by:**

| Dimension | Where it comes from in v2 | Cardinality |
|---|---|---|
| Channel, locale | Entries have channels and translations; blocks have channel and locale conditions ([#194](https://github.com/Setono/SyliusCMSPlugin/issues/194), [#198](https://github.com/Setono/SyliusCMSPlugin/issues/198)) | Low |
| Host-page context | Page scopes, host-page conditions, bindings and sources read `product`, `taxon` or `entry` ([#193](https://github.com/Setono/SyliusCMSPlugin/issues/193)) | Up to one variant per product or taxon |
| Customer group | Customer-group conditions and the entry's **audience**. Guest and "Signed in without a group" count as groups ([ADR 0010](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0010-entries-have-an-audience-not-visibility-conditions.md)) | Number of groups + 2 |
| Customer | Customer bindings ("Hi {first name}"), which #193 left for caching to decide | One per customer: never shareable |
| Clock | Date windows on entries and blocks, checked when content renders ([ADR 0009](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md)) | Changes at each window boundary |

**What expires output:**

- **A publish** of an entry, including a scheduled publish (a delayed Messenger message, ADR 0009).
- **What takes effect on save:**
  - placements ([ADR 0005](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0005-placements-take-effect-on-save.md))
  - `enabled` and the date window ([ADR 0009](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md))
  - media items: ADR 0013 already says "saving a media item has to expire cached output that uses it, like a publish does" ([ADR 0013](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0013-media-items-take-effect-on-save.md))
- **An import.** It lands in drafts, so only the parts that take effect on save expire anything at once ([ADR 0016](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0016-content-moves-between-environments-as-export-files-and-imports-land-in-drafts.md)).
- **A date-window boundary passing.** Nothing is saved at that moment.
- **Sylius data shown through bindings and sources**, such as a product's name, price or stock, or the products in a taxon. Sylius changes it, not the CMS.
- **A deploy that changes block-type templates or declarations.**

**What must never be cached:** the builder's preview, share links, and anything rendered with the forced "Shown to" or "Date" from the preview token ([#198](https://github.com/Setono/SyliusCMSPlugin/issues/198)).

**The contract #198 already settled:**

- Each condition kind declares what it reads: host-page context, channel or locale, the customer, or the clock.
- Output that read the customer never crosses customer groups in a shared cache, whether that's an HTTP cache or an application cache.
- Output that read the clock reports its next boundary.

## 2. Drupal: cacheability metadata on every render element

Drupal attaches three kinds of cacheability metadata to everything it renders. Parents merge their children's metadata, which Drupal calls bubbling.

- **Cache tags** say what data the output depends on, such as `node:5`, `node_list`, `node_list:article` or `config:system.performance`. Entities and config objects provide their tags themselves. `Cache::invalidateTags()` "invalidates items tagged with given tags, across *all* cache bins". List tags (`{entity_type}_list`) cover listings that change when any entity is added, changed or removed. Drupal sends the tags to reverse proxies and CDNs in a `Surrogate-Keys` or `Cache-Tag` header, and recommends supporting headers of up to about 16 KB ([cache tags](https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags)).
- **Cache contexts** are "analogous to HTTP's Vary header". They name what the output varies by. Contexts are hierarchical: `user` covers `user.permissions` and `user.roles`, and Drupal folds a set down to its most general member. Varying by `user.roles` or `user.permissions` gives one variant per role set, not one per user ([cache contexts](https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts)).
- **Max-age** is in seconds. `0` means uncacheable, and `Cache::PERMANENT` means only tags expire it. Drupal core "typically cache[s] permanently … and rel[ies] entirely on cache tags for invalidation" ([cache max-age](https://www.drupal.org/docs/drupal-apis/cache-api/cache-max-age)).

**How personalised parts are handled.** Drupal cuts them out of the cached page:

- A fragment built by a `#lazy_builder` is replaced by a placeholder when its metadata hits `renderer.config.auto_placeholder_conditions`. The defaults are `max-age: 0`, `contexts: ['session', 'user']` and no tags ([auto-placeholdering](https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering)).
- The rest of the page goes into the Dynamic Page Cache, for anonymous and authenticated users alike. The placeholders are filled on each request ([Dynamic Page Cache](https://www.drupal.org/docs/8/core/modules/dynamic-page-cache/overview)).
- The Internal Page Cache, for anonymous users only, "assumes that all pages served to anonymous users will be identical" and ignores contexts ([cache contexts](https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts)).

**Contexts discovered during rendering need cache redirects.** The cache key must be known before rendering, but some contexts only appear while rendering. Drupal stores a redirect entry that lists the contexts found, then the real entry under the fuller key. Since Drupal 10.2 this lives in a general `VariationCache` ([change record](https://www.drupal.org/node/3365546)). Redirects need their own invalidation ([#2548891](https://www.drupal.org/project/drupal/issues/2548891)), and they're a steady source of bugs. One example is a stale redirect left after content was published ([#3504114](https://www.drupal.org/project/drupal/issues/3504114)).

**Time-based content is Drupal's weak spot.** Bubbled max-age "does not work for anonymous users and the Drupal core Page Cache module". Varnish and CDNs don't respect it either, and the fix has been open for years ([cache max-age](https://www.drupal.org/docs/drupal-apis/cache-api/cache-max-age), [#2352009](https://www.drupal.org/project/drupal/issues/2352009)). The documented workarounds are a contributed module, or a custom tag that `hook_cron()` invalidates.

**Lesson for v2:** a boundary that one cache layer honours and the next ignores is the classic bug. The next boundary must reach every layer that stores the output, or that layer must not store it.

## 3. Sulu: HTTP cache, reference tags, target groups and block schedules

Sulu caches whole pages in a reverse proxy: Symfony HttpCache by default, or Varnish with xkey ([caching with Varnish](https://docs.sulu.io/en/latest/cookbook/caching-with-varnish.html)).

- **Tags come from what was rendered.** "Reference stores" collect the UUIDs of every document, snippet and media item the request rendered. `TagsSubscriber` writes them, plus the page's own UUID, into the response tags through FOSHttpCache's `SymfonyResponseTagger` ([TagsSubscriber](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/HttpCacheBundle/EventSubscriber/TagsSubscriber.php)). Publishing invalidates those tags, so "any changes you make in the admin interface are immediately available on your website" ([cookbook](https://docs.sulu.io/en/latest/cookbook/caching-with-varnish.html)).
- **Cache lifetime is per page, and blocks can shorten it.**
  - A page template sets a lifetime in seconds or as a cron expression. A cron expression resolves to the seconds until its next run ([CacheLifetimeResolver](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/HttpCacheBundle/CacheLifetime/CacheLifetimeResolver.php)).
  - A request-scoped `CacheLifetimeRequestStore` keeps the **minimum** of every lifetime reported during rendering and ignores negative values ([CacheLifetimeRequestStore](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/HttpCacheBundle/CacheLifetime/CacheLifetimeRequestStore.php)).
  - `CacheLifetimeEnhancer` takes the smaller of the page's and the request's lifetime. It sends that only to the reverse proxy in a separate TTL header (`SuluHttpCache::HEADER_REVERSE_PROXY_TTL`), and browsers get a fixed `max-age` ([CacheLifetimeEnhancer](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/HttpCacheBundle/CacheLifetime/CacheLifetimeEnhancer.php)).
- **Block schedules report the next boundary, which is exactly #198's clock contract.** Sulu 3's `ScheduleBlockVisitor` calls `setCacheLifetime(start - now)` and `setCacheLifetime(end - now)` for every fixed schedule. It does this whether or not the block shows right now, so a block that starts tomorrow still caps the page's lifetime. Weekly schedules look up to 7 days ahead for the next start. It reads "now" from the request analyzer, which is how preview forces a date ([ScheduleBlockVisitor](https://github.com/sulu/sulu/blob/3.0/packages/content/src/Application/PropertyResolver/BlockVisitor/ScheduleBlockVisitor.php)).
- **Target groups vary the cache only when content read them.**
  - `TargetGroupStore::getTargetGroupId()` sets an "influenced content" flag on any read that isn't internal ([TargetGroupStore](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/AudienceTargetingBundle/TargetGroup/TargetGroupStore.php)).
  - Only then does `TargetGroupSubscriber` add `Vary: X-Sulu-Target-Group` ([TargetGroupSubscriber](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/AudienceTargetingBundle/EventListener/TargetGroupSubscriber.php)).
  - Pages that don't use targeting keep one shared variant.
- **How the proxy knows the group.**
  - On a first visit, the proxy (in VCL, or `AudienceTargetingCacheListener` in Symfony HttpCache) makes an internal request to `/_sulu_target_group`. It copies the group into an `X-Sulu-Target-Group` request header, sets the `_svtg` and `_svs` cookies, and restarts the original request.
  - Any response that varies on the header gets `max-age=0` and `s-maxage=0` for downstream, so only the proxy keeps per-group copies ([sulu.vcl](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/AudienceTargetingBundle/Resources/varnish/sulu.vcl), [AudienceTargetingCacheListener](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/AudienceTargetingBundle/EventListener/AudienceTargetingCacheListener.php)).
  - This is the FOSHttpCache user-context pattern (§6), specialised for target groups.

**Lessons for v2:**

- Sulu's "vary only if read" is the runtime form of #198's "condition kinds declare what they read".
- Its minimum-lifetime store, fed by every scheduled block including inactive ones, is a ready model for "report the next boundary".

## 4. Shopware: a context hash, rule areas, ESI header and footer, and buffered invalidation

- **Shopware opts its cacheable routes out of Symfony's session rule.** For routes with an HTTP-cache attribute, `CacheResponseSubscriber` sets `AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER`, so using the session doesn't make the response private ([CacheResponseSubscriber](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Adapter/Cache/Http/CacheResponseSubscriber.php)). Sylius 1.14 doesn't do this (§7).
- **The cache key is a hash of the shopper's state, not their identity.**
  - `CacheHeadersService::buildCacheHash()` hashes the IDs of the rules that matched, plus the version, currency, tax state, logged-in state, language and a tax-rule fingerprint. Extensions can add parts through `HttpCacheCookieEvent`.
  - With the newer flags, only the rules in the **rule areas** that matter to the request go into the hash ([CacheHeadersService](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Adapter/Cache/Http/CacheHeadersService.php)).
  - The hash travels in the `sw-cache-hash` cookie, which is set only when the state differs from the default. The docs stress keeping the number of permutations "as low as possible" ([HTTP cache concept](https://developer.shopware.com/docs/concepts/framework/http_cache.html)).
  - Customer groups enter the key the way any rule does: shoppers whose rules match identically share a cache entry.
- **It guards against cache poisoning.** Under `CACHE_REWORK` (behind a flag since 6.7.6.0, the default in 6.8), a request whose client hash doesn't match the hash the server computes isn't cached ([CacheResponseSubscriber](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Adapter/Cache/Http/CacheResponseSubscriber.php)).
  - The older mechanism bypassed the cache for `logged-in` and `cart-filled` states. Its `sw-states` cookie is deprecated for removal in 6.8 ([HTTP cache concept](https://developer.shopware.com/docs/concepts/framework/http_cache.html)).
- **Listings live on their TTL.** Product listings, search and category listings "are not tagged with every returned entity" and rely on their TTL. The docs say plainly that listings "cannot guarantee strict, immediate consistency under caching" ([HTTP cache concept](https://developer.shopware.com/docs/concepts/framework/http_cache.html)).
- **The header and footer are ESI fragments since 6.7.**
  - They're "loaded with sub-requests and are therefore no longer dependent on the current page data", so they can stay cached longer.
  - Page-dependent data passes as `headerParameters` and `footerParameters`, which "can only contain scalar values, as they are also query parameters for the ESI routes" ([customize header and footer](https://developer.shopware.com/docs/v6.7/guides/plugins/plugins/storefront/customize-header-footer.html)).
  - A shop that had per-page random footer content reported it frozen after the upgrade. The report was closed as not planned ([shopware#14723](https://github.com/shopware/shopware/issues/14723)).
- **Invalidation can be buffered.**
  - `CacheInvalidator::invalidate()` can store tags instead of purging them, unless forced. `InvalidateCacheTask` later purges what was stored ([CacheInvalidator](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Adapter/Cache/CacheInvalidator.php)).
  - The HTTP cache and the object cache share tags, so one invalidation clears both. Varnish is supported through xkey, with soft purge optional, and the BAN method is deprecated ([reverse HTTP cache](https://developer.shopware.com/docs/guides/hosting/infrastructure/reverse-http-cache.html)).

**Lessons for v2:**

- Rule areas are Shopware's version of "declare what you read": only state that could change the route's output enters the key.
- ESI with scalar parameters is a proven way to cache a region separately from a page.
- Listings and product data are where tag invalidation gives way to TTLs.

## 5. Craft: the `{% cache %}` tag with element tags and expiry dates

- **The tag caches a region of a template.**
  - By default it caches per URL, ignoring the query string. `globally` caches per site, and `using key` sets the key. `for` or `until` set the duration, and `if` or `unless` make it conditional, such as `{% cache unless currentUser %}`.
  - "Your template caches will automatically clear when any elements … within the tags are saved or deleted". A new element that a cached query would return clears it too.
  - The docs advise to "avoid caching `{{ csrfInput() }}`, form fields, or parts of templates where the markup is highly dynamic or personalized" ([Twig tags: cache](https://craftcms.com/docs/5.x/reference/twig/tags.html#cache)).
- **Rendering collects tags and a duration.**
  - `startCollectingCacheInfo()` and `stopCollectingCacheInfo()` bracket the rendering. Every element that's rendered adds `element`, `element::<class>` and `element::<id>`.
  - `setCacheExpiryDate()` keeps the shortest positive duration seen. Nested collections pass the shorter duration up to their parent, Craft's form of bubbling ([Elements service](https://github.com/craftcms/cms/blob/5.x/src/services/Elements.php)).
- **Only elements that were returned can shorten the duration.** Element queries call `setCacheExpiryDate()` with the expiry date of each element they return ([ElementQuery](https://github.com/craftcms/cms/blob/5.x/src/elements/db/ElementQuery.php)).
  - Reading the source, no code reports the post date of an entry that is still pending. A pending entry isn't in the results, so a cached listing can't know it should expire when that entry goes live. This is an inference from the source, which has no other call sites ([code search](https://github.com/search?q=repo%3Acraftcms%2Fcms+setCacheExpiryDate&type=code)).

**Lesson for v2:** listings and sources filter on the date window in SQL (ADR 0010, [#205](https://github.com/Setono/SyliusCMSPlugin/issues/205)). They hit the same gap: the next *start* among rows the query left out is invisible unless something asks for it.

## 6. Symfony, FOSHttpCache and Varnish: the building blocks

**Sessions make responses private.**

- Symfony: "Whenever the session is started during a request, Symfony turns the response into a private non-cacheable response."
- A response can opt out with `AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER`.
- Caching per user group is "out of the scope of Symfony", and the docs point to FOSHttpCacheBundle ([HTTP cache and user sessions](https://symfony.com/doc/6.4/http_cache.html#http-caching-and-user-sessions)).

**The Cache component supports tags.**

- `$item->tag([...])` inside a `get()` callback, then `TagAwareCacheInterface::invalidateTags([...])` ([cache tags](https://symfony.com/doc/6.4/cache.html#using-cache-tags)).
- `cache.adapter.redis_tag_aware` keeps a Redis set per tag. Reads take one call, but invalidating a tag deletes its items, and that gets slower as the tag grows. The sets never expire, so Redis needs `maxmemory-policy` `noeviction` or `volatile-*`.
- `tags: true` on any other adapter wraps it in `TagAwareAdapter`. That stores a version per tag, so an invalidation costs the same whatever the tag's size.
- Stampedes are handled by a built-in lock and probabilistic early expiration (`beta`) ([cache component](https://symfony.com/doc/6.4/components/cache.html)). Recomputation can move to a Messenger worker with `early_expiration_message_bus` ([async computation](https://symfony.com/doc/6.4/cache.html#computing-cache-values-asynchronously)).

**ESI and fragments.**

- With `render_esi()`, "the variables passed through `render_esi` also become part of the cache key". Each fragment sets its own caching rules.
- Without a gateway cache that sends `Surrogate-Capability: ESI/1.0`, "Symfony will just merge the included page content within the main one as it would have done if you had used `render()`".
- Fragment URLs go through `/_fragment` and are signed ([ESI](https://symfony.com/doc/6.4/http_cache/esi.html)).
- `ResponseCacheStrategy` sets the assembled response's TTL to "the smallest TTL received", and a directive like `private` in any fragment carries over to it ([ResponseCacheStrategy](https://github.com/symfony/symfony/blob/6.4/src/Symfony/Component/HttpKernel/HttpCache/ResponseCacheStrategy.php)).
- `render_hinclude()` loads a fragment with JavaScript after the page arrives.
- Symfony warns that embedded controllers "can have a significant impact on the application performance if you embed lots of controllers" ([templates](https://symfony.com/doc/6.4/templates.html#embedding-controllers)).

**FOSHttpCache user context.**

1. The proxy sends a hash request to `/_fos_user_context_hash`.
2. The app returns an `X-User-Context-Hash`.
3. The proxy restarts the request with that header, and context-dependent responses send `Vary: X-User-Context-Hash`.

The hash response can itself be cached per cookie. "If every user has their own hash, you probably don't want to cache at all." Individual parts should be loaded by AJAX or ESI ([user context](https://foshttpcache.readthedocs.io/en/latest/user-context.html)). The bundle ships a `role_provider`, and custom context providers can add, for example, a customer group ([bundle user context](https://foshttpcachebundle.readthedocs.io/en/latest/features/user-context.html)). Varnish and Symfony HttpCache are supported.

**FOSHttpCache tagging.**

- `ResponseTagger::addTags()`, a `#[Tag]` attribute, or the Twig function `fos_httpcache_tag()`.
- `CacheManager::invalidateTags()` flushes "after the response has been sent" ([tagging](https://foshttpcachebundle.readthedocs.io/en/latest/features/tagging.html)).

**Symfony HttpCache can't purge by tag on its own.** "Symfony's HttpCache store implementation does not support tags". It needs `toflar/psr6-symfony-http-cache-store` and its `Psr6Store`, has no banning, and is "less efficient than using Varnish or NGINX" ([Symfony cache configuration](https://foshttpcache.readthedocs.io/en/latest/symfony-cache-configuration.html)).

**Versions that fit Sylius 1.14.** FOSHttpCacheBundle 3.x requires PHP `^8.1` and Symfony `^6.4` (3.4.1, 2026-06-08), so a 1.14 install on Symfony 5.4 would need the 2.x line. `toflar/psr6-symfony-http-cache-store` 4.3.1 supports `symfony/http-kernel ^6.0 || ^7.0 || ^8.0` ([Packagist API](https://repo.packagist.org/p2/friendsofsymfony/http-cache-bundle.json)).

**Varnish.**

- The xkey vmod "adds secondary hashes to objects, allowing fast purging on all objects with this hash key". Keys come from an `xkey` response header, separated by spaces or commas.
- `softpurge` keeps the object available for grace and conditional requests, where `purge` removes it ([vmod_xkey.vcc](https://github.com/varnish/varnish-modules/blob/master/src/vmod_xkey.vcc)).
- Varnish implements "a small subset of ESI" (`esi:include`, `esi:remove` and `<!--esi …-->`), and included fragments "can have individual cache policies". "Content substitution based on variables and cookies is not implemented" ([Varnish ESI](https://www.varnish.org/docs/users-guide/esi/)).

## 7. Side by side

| | Unit cached | What it varies by | How it's invalidated | Time boundaries | Personalised parts |
|---|---|---|---|---|---|
| **Drupal** | Every render element, plus whole pages | Cache contexts, declared and bubbled; redirects for contexts found while rendering | Tags; entity and list tags provided automatically | Max-age bubbles, but the page cache and proxies ignore it | Auto-placeholdered (`user`, `session`, max-age 0) |
| **Sulu** | Whole page in a reverse proxy | URL, plus `X-Sulu-Target-Group` only if content read it | UUID tags from reference stores; xkey | Minimum of page lifetime and block schedules, including inactive ones | Target group via a proxy-side lookup and `Vary` |
| **Shopware** | Whole page, header and footer as ESI | Hash of matched rules (by rule area), currency, language, tax, logged-in state | Entity tags shared by the object and HTTP caches, can be buffered; xkey | TTL from caching policies; listings rely on TTL | Rules enter the hash; state mismatch means no cache |
| **Craft** | A template region (`{% cache %}`) | URL, or a key the developer chooses | `element::<id>` tags, and query results | Shortest expiry date of returned elements | Developer excludes them (`unless currentUser`) |
| **FOSHttpCache** | HTTP responses and ESI fragments | `Vary: X-User-Context-Hash` from context providers | Tags; xkey, BAN or the Psr6Store | Whatever the app sets | Hash per group; per-user output by AJAX or ESI |

## 8. What this means for v2 on Sylius 1.14

**The platform facts that fix the options:**

- **Every 1.14 shop page is private.** The layout renders the cart, security and switcher widgets inline, and the product page renders the add-to-cart CSRF token, so the session is used on every page ([#190 findings §7](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md#7-http-caching)). Turning that around means overriding Sylius templates (ESI or JavaScript for the widgets) and adding a proxy. That's project work, which the plugin can enable but not do.
- **Slots render inline inside template events,** in the main request with only the call-site context ([#190 findings §3](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md#3-shop-template-events)). The customer group is read through `CustomerContextInterface`, which reads the shop user from the security token, so it needs the session ([§4](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md#4-channel-locale-and-customer-group-apis)).
- **An entry at its own URL is served by a Sylius shop route** ([ADR 0015](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0015-entry-urls-are-matched-last-by-a-route-that-looks-them-up.md)). Unless its layout leaves out the shop's header widgets, it shares the same session-bound fragments.

**So the HTTP layer is a later, opt-in layer, and the cache that works on day one is an application cache.**

### 8.1 The building block every option needs: cacheability metadata

The other CMSs show what this looks like.

- **Each rendered unit carries three things.** Each rendered snippet or entry body carries **contexts** (channel, locale, the host-page keys it reads, customer group, customer), **tags** (entry UUIDs, media item UUIDs, slots, list tags per content type, Sylius entities) and a **max-age** (seconds to the next boundary). Children merge their metadata into their parents: contexts and tags are unioned, and the lowest max-age wins. That's Drupal's model and Craft's buffers.
- **v2 can know the contexts before it renders.** Condition kinds, bindings, sources and block types already declare what they read and which context keys they need (#193, #198, [#206](https://github.com/Setono/SyliusCMSPlugin/issues/206)). The published block tree is JSON. A pass over the tree, which could be stored at publish, therefore gives the full variation set and every date-window boundary, including those of blocks hidden right now, as Sulu does. That avoids Drupal's cache redirects. The exceptions are sources and block types whose reads depend on runtime data. They need a declaration that's broad enough, or they mark the output uncacheable.
- **The customer group becomes a bucket in the key:** a group code, Guest or Signed in without a group. It goes in only when something read it, like Sulu's "influenced content".
- **Customer bindings vary per customer,** so they're max-age 0. They stay out of shared caches altogether: they render uncached, or as a placeholder (Drupal) or client-side fragment (FOSHttpCache's advice).

The same metadata can then feed every option below. It can also drive capped slots, which #198 decides without rendering:

- A slot's winners depend on its placements, each snippet's state, its audience and date window, and the page scope's host-page keys.
- A slot's metadata therefore unions those of all its candidate placements. That includes the date-window boundaries of placements that don't match yet.

### 8.2 Options for #208

**Option A: no output cache in v2.0; cache only the inputs.**

- Render every slot and entry on each request.
- Cache the cheap-to-key inputs: published block trees, candidate placements per slot and channel, and source results under their own tags and TTLs. Bestsellers are already precomputed ([#204](https://github.com/Setono/SyliusCMSPlugin/issues/204)).
- Ship the cacheability metadata and invalidation events from §8.1 anyway, so output caching can come later without remodeling.
- *For:* the simplest to get right. Every change is visible at once. Preview needs no special handling. Customer bindings are free.
- *Against:* each page pays for Twig rendering of every matching snippet and their source queries, up to about 20 stock slots. The settled contract is only exercised once a cache is added.

**Option B: an application-level cache of rendered output in a Symfony tag-aware pool.**

- **The unit:** each snippet's output in a slot, and each entry's block tree. Slot resolution (page scope plus capped-slot winners) stays per request, since it reads only cached placement data.
- **The key:** unit UUID and published revision, channel, locale, the values of the host-page keys it reads, the customer-group bucket only if read, and a code version. The code version moves with block-type templates and declarations, like Drupal's `deployment_id` context.
- **Tags:** the unit, every snippet and entry it includes or lists, media items, the slot, and the content type's list tag.
- **TTL:** the next boundary.
- **When to invalidate:**
  - on publish, scheduled publish included
  - on saving a placement, `enabled`, the date window or the audience
  - on saving a media item
  - after an import's on-save changes
  - when a customer group is deleted
- *For:* it works on stock 1.14 with no proxy. It honours #198 in the layer that actually stores output. It uses only Symfony Cache, already in every Sylius app.
- *Against:*
  - Invalidation bugs are the risk.
  - Cardinality grows with product-scoped content: products × channels × locales × groups.
  - It needs a tag-aware pool. In production that should be Redis with the right eviction policy (§6).
  - **Sylius data inside the output** is the hard part. Bound product names and source results need either tags on Sylius entities, which means listening to Doctrine flushes on products and taxons, or a short TTL, as Shopware uses for listings.
  - Prices, stock and catalog promotions change without a CMS save, so cached product cards go stale until their TTL runs out.
  - Product cards could be held as placeholders and rendered per request instead.

**Option C: HTTP-cache metadata as an opt-in for projects with a proxy (on top of A or B).**

- On responses the plugin controls, such as entry URLs and fragment routes, the plugin emits:
  - tags through FOSHttpCache's `ResponseTagger` (an optional dependency)
  - `s-maxage` capped at the next boundary
  - for output that read the customer group, a `Vary` on a context hash, from a FOSHttpCache context provider or a Shopware-style hash
- Slots can be rendered with `render_esi()` and scalar context parameters (`product` ID, `taxon` ID), as Shopware 6.7 does for its header and footer. Behind Varnish or Symfony HttpCache, a slot is then cached even when its page is private. Without a proxy, Symfony falls back to an inline sub-request.
- *For:* the only route to proxy-level caching. The metadata comes free once §8.1 exists.
- *Against:*
  - Real gains need the project to make shop pages public: ESI or JavaScript for the cart, security and switcher widgets, CSRF handled, and `NO_AUTO_CACHE_CONTROL_HEADER`. That's work Sylius 1.14 doesn't support.
  - ESI slots with no proxy cost one sub-request each, and Symfony warns about many embedded controllers.
  - Symfony HttpCache needs the Psr6Store for tag purges, and FOSHttpCacheBundle 3.x needs Symfony 6.4.
  - Sulu's restart-based group lookup shows how much proxy configuration personalised caching takes.

**Option D: render customer-dependent parts in the browser (on top of any option).**

- Blocks whose metadata reads the customer render as a placeholder filled by a JavaScript request (`render_hinclude()` or the plugin's own endpoint). Everything else stays shareable, even across customer groups.
- *For:* this is how Drupal, Craft's docs and FOSHttpCache all keep personalised parts out of shared caches. Customer bindings fit it naturally.
- *Against:*
  - Layout shift, an extra request, and JavaScript in blocks that are supposed to be theme-agnostic ([ADR 0017](https://github.com/Setono/SyliusCMSPlugin/blob/2.x/docs/adr/0017-shipped-blocks-are-theme-agnostic.md)).
  - On 1.14 the page is private anyway, so it pays off only with a proxy or with Option B's shared entries.

### 8.3 Decisions #208 has to make, whichever option wins

1. **How fine-grained the cached unit is.** It could be per block (Drupal), per snippet or entry, per slot, or per page (Sulu, Shopware). Each step coarser means fewer lookups but more variation from the contexts merged into it.
2. **Where the variation comes from.** It can come from a static pass over the published tree, or from metadata collected while rendering, with redirects or Sulu-style "vary if read". The static pass fits v2's declared reads and avoids redirect bugs.
3. **The next boundary for SQL-filtered listings and sources.** Their date windows are in rows the query left out, which is Craft's gap. Two ways to close it:
   - Run an extra query for the soonest future start or end among excluded rows, which keeps ADR 0009's "the date window needs no worker".
   - Schedule a delayed Messenger message at each boundary that invalidates the tags. That needs the worker that scheduled publish already requires.
4. **Sylius-owned data in CMS output.** Tag it through Doctrine listeners on Sylius entities, use a TTL, or keep product cards out of cached output.
5. **When to invalidate.** Invalidate after the transaction commits: FOSHttpCache flushes after the response is sent, and Shopware can buffer tags for a scheduled task. Immediate invalidation suits editors. Buffered invalidation suits bulk imports and saves of media items with many uses.
6. **Bypassing preview.** The builder's preview, share links and forced "Shown to" and "Date" never read or write a cache. Drafts, revisions and share-link entries built from JSON (ADR 0009) must never get tags that let them pollute a cache.
7. **Customer bindings.** Allow them only as uncached or client-side output, or leave them out of v2.0 (#193 deferred them to this decision).
8. **Operations.** A "clear CMS cache" action and CLI (`cache:pool:invalidate-tags` exists since Symfony 6.1), and stampede protection from the Cache component's lock and `beta`.

## Sources

- Drupal: [cache contexts](https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts), [cache tags](https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags), [cache max-age](https://www.drupal.org/docs/drupal-apis/cache-api/cache-max-age), [auto-placeholdering](https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering), [Dynamic Page Cache](https://www.drupal.org/docs/8/core/modules/dynamic-page-cache/overview), [VariationCache change record](https://www.drupal.org/node/3365546), issues [#2352009](https://www.drupal.org/project/drupal/issues/2352009), [#2548891](https://www.drupal.org/project/drupal/issues/2548891), [#3504114](https://www.drupal.org/project/drupal/issues/3504114)
- Sulu: [caching with Varnish](https://docs.sulu.io/en/latest/cookbook/caching-with-varnish.html), [audience targeting](https://docs.sulu.io/en/latest/bundles/audience_targeting.html), and source in [`HttpCacheBundle`](https://github.com/sulu/sulu/tree/2.6/src/Sulu/Bundle/HttpCacheBundle), [`AudienceTargetingBundle`](https://github.com/sulu/sulu/tree/2.6/src/Sulu/Bundle/AudienceTargetingBundle) and [`ScheduleBlockVisitor`](https://github.com/sulu/sulu/blob/3.0/packages/content/src/Application/PropertyResolver/BlockVisitor/ScheduleBlockVisitor.php)
- Shopware: [HTTP cache concept](https://developer.shopware.com/docs/concepts/framework/http_cache.html), [reverse HTTP cache](https://developer.shopware.com/docs/guides/hosting/infrastructure/reverse-http-cache.html), [customize header and footer](https://developer.shopware.com/docs/v6.7/guides/plugins/plugins/storefront/customize-header-footer.html), source in [`Adapter/Cache`](https://github.com/shopware/shopware/tree/trunk/src/Core/Framework/Adapter/Cache), [shopware#14723](https://github.com/shopware/shopware/issues/14723)
- Craft: [`{% cache %}`](https://craftcms.com/docs/5.x/reference/twig/tags.html#cache), [`services/Elements.php`](https://github.com/craftcms/cms/blob/5.x/src/services/Elements.php), [`elements/db/ElementQuery.php`](https://github.com/craftcms/cms/blob/5.x/src/elements/db/ElementQuery.php)
- Symfony 6.4: [HTTP cache](https://symfony.com/doc/6.4/http_cache.html), [ESI](https://symfony.com/doc/6.4/http_cache/esi.html), [cache](https://symfony.com/doc/6.4/cache.html), [cache component](https://symfony.com/doc/6.4/components/cache.html), [templates](https://symfony.com/doc/6.4/templates.html#embedding-controllers), [`ResponseCacheStrategy`](https://github.com/symfony/symfony/blob/6.4/src/Symfony/Component/HttpKernel/HttpCache/ResponseCacheStrategy.php)
- FOSHttpCache: [user context](https://foshttpcache.readthedocs.io/en/latest/user-context.html), [Symfony HttpCache configuration](https://foshttpcache.readthedocs.io/en/latest/symfony-cache-configuration.html), bundle [tagging](https://foshttpcachebundle.readthedocs.io/en/latest/features/tagging.html) and [user context](https://foshttpcachebundle.readthedocs.io/en/latest/features/user-context.html)
- Varnish: [`vmod_xkey.vcc`](https://github.com/varnish/varnish-modules/blob/master/src/vmod_xkey.vcc), [ESI](https://www.varnish.org/docs/users-guide/esi/)
- Sylius 1.14: [#190 findings](https://github.com/Setono/SyliusCMSPlugin/blob/research/sylius-1-14-constraints/docs/research/sylius-1-14-constraints.md)
