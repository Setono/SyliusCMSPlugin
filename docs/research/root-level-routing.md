# Root-level CMS page URLs next to Sylius 1.14 shop routes

Research for [#202](https://github.com/Setono/SyliusCMSPlugin/issues/202) (URLs and SEO), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186). Gathered 2026-11-13.

**Question.** Can v2 serve pages at `/{_locale}/{path}` with no fixed prefix (`/en_US/about-us`, `/en_US/about/team`) next to Sylius's own shop routes, robustly in real projects? Or should it use a fixed but configurable prefix such as `/{_locale}/pg/{path}`?

**Sources.**

- Symfony 6.4 source at tag `v6.4.50` and the [Symfony 6.4 routing docs](https://symfony.com/doc/6.4/routing.html).
- Sylius source at tag `v1.14.21` and Sylius-Standard branch `1.14`.
- Plugin source: BitBag, Sylius CmsPlugin, Monsieur Biz and Happy CMS, with their issues and PRs.
- For the brief comparison: Shopware, Sulu, Magento and Symfony CMF.

**Experiments.** I ran the real ShopBundle routing from Sylius `v1.14.21` through Symfony 6.4.50 routing, loaded in Sylius-Standard 1.14 order. URLs were matched with `RedirectableCompiledUrlMatcher`, FrameworkBundle's production matcher. The harness is described in [Appendix: experiment setup](#appendix-experiment-setup). Its result tables are marked "experiment".

## Short answer

**Root-level is practical, with caveats.** Symfony 6.4 has the pieces to do it cleanly. The problems people hit in practice come from three avoidable patterns:

1. **Relying on YAML file order.** Sylius-Standard loads `config/routes/*.yaml` alphabetically, so a plugin file such as `setono_sylius_cms.yaml` or `monsieurbiz_….yaml` sorts *before* `sylius_shop.yaml`. A catch-all there swallows `/products/…`, `/cart`, `/login` and the rest of the shop (experiment, §1).
2. **Route conditions that read Sylius's locale context.** At routing time the `_locale` attribute doesn't exist yet. The context therefore falls back to `Accept-Language`, which is the root cause of Monsieur Biz's 404 reports CP#14 and CP#78 (§3).
3. **No check that a page path is free.** Editors can create pages that a route already owns, or that a later route takes over.

**Recommended mechanism:**

- **A custom route loader** (`type: setono_sylius_cms`) that the app imports once under its shop prefix (`/{_locale}` plus Sylius's `_locale` requirement). The loader emits the page route with a strongly **negative priority**, so it is matched after every other route whatever the file order.
- **Requirement `path: .+(?<!/)`**, the same one Sylius uses for taxon slugs.
- **A route condition** `service('…').exists(params['_locale'], params['path'])`. It looks the page up by channel (from the host), locale and path, and is evaluated only for URLs that no other route matched.
- **A save-time conflict check** that runs the candidate URL through the router's matcher.
- **A console check** for deploys, to find pages a newly added route now shadows.

**Main residual risks:**

- A route added *after* a page was published silently wins over the page. The deploy-time check reduces this risk but doesn't remove it.
- Every URL that no route matches costs one indexed lookup, two for a 404 because of the trailing-slash retry, unless the result is memoised.
- The condition runs before the firewall and before `_locale` is applied. It can only check existence, so audience checks stay in the controller.
- Editors lose a set of reserved paths (`products/…`, `taxons/…`, `cart`, `login`, `register`, `contact`, `checkout…`, `account/…`, `order/…` and any app or plugin route).

**One config value for the prefix, where an empty string means root, is sensible.** The same loader builds `/{path}` or `/{prefix}/{path}`, and the route name and parameters don't change, so templates and URL generation are identical in both modes. A loader is needed anyway, because YAML can't express route priority. Changing the value later moves every URL, so it needs its own redirect.

---

## 1. How Sylius-Standard 1.14 loads routes

**Load order.**

- Sylius-Standard's [`Kernel`](https://github.com/Sylius/Sylius-Standard/blob/1.14/src/Kernel.php) uses `MicroKernelTrait` and doesn't override `configureRoutes()`. Its [`composer.json`](https://github.com/Sylius/Sylius-Standard/blob/1.14/composer.json) pins Flex to Symfony `^6.4`.
- The trait imports, in this order ([MicroKernelTrait L74–L90](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Bundle/FrameworkBundle/Kernel/MicroKernelTrait.php#L74-L90)):
  1. `config/routes/{env}/*.{php,yaml}`;
  2. `config/routes/*.{php,yaml}`;
  3. `config/routes.yaml`;
  4. attribute routes on the kernel class.
- Glob results are sorted with `strnatcmp` ([GlobResource L153](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Config/Resource/GlobResource.php#L153)), so files load alphabetically.

**Route files in Sylius-Standard 1.14** ([config/routes](https://github.com/Sylius/Sylius-Standard/tree/1.14/config/routes)):

- `annotations.yaml` loads the app's `src/Controller` and comes first.
- `js_translation`, `liip_imagine`, `sylius_admin`, `sylius_adyen`, `sylius_api`, `sylius_mollie`, `sylius_refund`, `sylius_shop`, `sylius_test_plugin` and `web_profiler` follow, then [`config/routes.yaml`](https://github.com/Sylius/Sylius-Standard/blob/1.14/config/routes.yaml) (PayPal webhook).

**[`sylius_shop.yaml`](https://github.com/Sylius/Sylius-Standard/blob/1.14/config/routes/sylius_shop.yaml) imports:**

- **The shop routes.** ShopBundle's `routing.yml` comes in with `prefix: /{_locale}` and `_locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$`. PayPal's shop routes come in the same way.
- **Routes outside `/{_locale}`:** Payum, `/` (`sylius_shop_default_locale`, the locale switch to the default locale) and `/.well-known/change-password`.
- **Other prefixes:** admin lives under `/%sylius_admin.path_name%` and the API under `%sylius.security.api_route%`.

**Shop routes that matter here** ([routing.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/routing.yml)):

- **Root-level paths** under `/{_locale}`: `/` (homepage), `/login`, `/register`, `/forgotten-password` and similar, `/products/{slug}`, `/taxons/{slug}`, `/switch-currency/{code}` and `/switch-locale/{code}`.
- **Prefixed groups:** `/ajax`, `/_partial`, `/cart`, `/checkout`, `/contact`, `/order` and `/account`.
- **Taxons** use `slug: .+(?<!/)`. That allows slashes, and the negative lookbehind keeps a trailing slash out of the slug ([product.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/routing/product.yml)).

**Consequence.** A plugin that ships `config/routes/setono_sylius_cms.yaml` sorts after `annotations.yaml` but **before** `sylius_admin`, `sylius_api` and `sylius_shop`. Plain YAML ordering can't put a catch-all last. The experiment below loads a catch-all `/{_locale}/{path}` with no priority from that file name:

| URL | Matched route (no priority, experiment) |
|---|---|
| `/en_US/products/foo` | `setono_sylius_cms_page_show` (product route shadowed) |
| `/en_US/taxons/a/b` | `setono_sylius_cms_page_show` (taxon route shadowed) |
| `/en_US/cart/`, `/en_US/login`, `/en_US/account/dashboard` | `setono_sylius_cms_page_show` |
| `/en_US/_partial/cart/summary` | `setono_sylius_cms_page_show` (breaks the layout's sub-requests) |
| `/en_US/` | `sylius_shop_homepage` (survives only because `{path}` needs at least one character) |
| `/en_US/brands/acme` (app attribute route) | `app_brand_show` (survives because `annotations.yaml` sorts first) |

## 2. Guaranteeing the catch-all is matched last

### Route priority in Symfony 6.4

- `RouteCollection::add($name, $route, int $priority = 0)` stores a priority. `all()` sorts routes by priority, highest first, and keeps load order within the same priority ([RouteCollection L88–L112](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/RouteCollection.php#L88-L112)).
- `addCollection()` carries priorities into the parent collection ([L176–L187](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/RouteCollection.php#L176-L187)).
- The compiled matcher is dumped from `all()` ([CompiledUrlMatcherDumper L69](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Matcher/Dumper/CompiledUrlMatcherDumper.php#L69)), so the sort holds at runtime.
- **Where priority can be set:**
  - **Attribute routes:** `#[Route(priority: …)]` ([AttributeClassLoader L210](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Loader/AttributeClassLoader.php#L210)).
  - **Programmatic routes:** any loader can call `add(..., $priority)`.
  - **Not YAML, XML or PHP-config routes.**
    - The YAML loader's allowed keys have no `priority`, in 6.4 ([YamlFileLoader L36–L38](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Loader/YamlFileLoader.php#L36-L38)) and still in [7.4](https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/Routing/Loader/YamlFileLoader.php#L38-L40).
    - The docs say: "In YAML and XML you can move the route definitions up or down in the configuration file to control their priority. In routes defined as PHP attributes this is much harder to do, so you can set the optional `priority` parameter" ([Priority Parameter](https://symfony.com/doc/6.4/routing.html#priority-parameter)).
  - **Experiment:** a YAML route with `priority: -10` is rejected as an unsupported key.
- **Imports keep priorities.** A YAML import of a custom-loader resource with `prefix: /{_locale}` and the `_locale` requirement keeps the loader's `-1000`. Experiment: `setono_sylius_cms_page_show  /{_locale}/{path}  priority=-1000`.

**So the plugin should ship a [custom route loader](https://symfony.com/doc/6.4/routing/custom_route_loader.html).** The app imports it the way it already imports Sylius's shop routes:

```yaml
# config/routes/setono_sylius_cms.yaml (app)
setono_sylius_cms_shop:
    resource: .
    type: setono_sylius_cms
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```

An attribute route with `priority` also works. But its path can't vary with a config value: `%param%` placeholders are resolved in route paths ([FrameworkBundle Router::resolveParameters](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Bundle/FrameworkBundle/Routing/Router.php#L112)), yet an empty prefix would leave `//`. The loader is the cleaner fit.

### Results with priority -1000 (experiment)

The page route is imported from `setono_sylius_cms.yaml` at priority `-1000`, with no condition. A later plugin route `wishlist_index` is loaded from `config/routes.yaml`.

| Request | Result | Note |
|---|---|---|
| `GET /en_US/about-us` | page route, `path=about-us` | |
| `GET /en_US/about/team` | page route, `path=about/team` | slashes work with `.+` |
| `GET /en_US/products/foo`, `/taxons/a/b`, `/login`, `/account/dashboard`, `/_partial/cart/summary` | Sylius routes | not shadowed |
| `GET /en_US/products/foo/`, `/en_US/taxons/a/b/` | 301 to the path without slash | Sylius's trailing-slash redirect still works |
| `GET /en_US/cart` | 301 to `/en_US/cart/` | |
| `GET /en_US/wishlist` (loaded **after** the page route) | `wishlist_index` | later routes are not shadowed |
| `GET /en_US/brands/acme` (app route) | `app_brand_show` | |
| `GET /en_US/wishlist/add/5` (POST-only route) | page route | 404 from the CMS instead of 405 |
| `POST /en_US/nope` | 405 (Allow: GET, HEAD) | instead of 404, cosmetic |
| `GET /en_US/no-such-page` | page route | every unknown shop URL reaches the CMS |
| `GET /api/v2/shop/nothing` | page route, `_locale=api` | `api` matches the 2–4-letter locale regex |
| `GET /wp/wp-login.php` | page route, `_locale=wp` | see the soft-404 caveat below |
| `GET /admin/`, `/blog/hello` (app route without locale) | their own routes | |

### What still breaks without more work

- **Later routes shadow pages, silently.** Priority 0 routes added later (a new plugin, an app controller) win over the page route, which is correct. But a page whose path such a route now matches becomes unreachable without any error. The fix is the conflict check and deploy-time check in §6.1.
- **Routes with an even lower priority are shadowed by an unconditional catch-all**, for example another plugin's catch-all. A condition on the page route (§3) lets them through.
- **Unknown URLs become soft 404s.** A URL whose first segment fits the locale regex (`wp`, `api`, `shop`, `blog`…) now matches the page route. Under the shop firewall (`^/(?!admin|api/.*|api$|media/.*)[^/]++`, [config.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/app/config.yml#L12)), Sylius's `NonChannelLocaleListener` (kernel.request, priority 10) sees a `_locale` attribute that isn't a channel locale. It then **redirects to the default-locale homepage** instead of letting a 404 happen ([NonChannelLocaleListener](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/EventListener/NonChannelLocaleListener.php), [url.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/services/integrations/locale/url.xml#L27-L35)). Without the catch-all, `/wp/wp-login.php` is a plain 404. A condition that checks existence (§3) restores the router-level 404. Read from source, not tested over HTTP.
- **Trailing slashes depend on the requirement.**
  - With `.+`, the slash is captured: `/en_US/about/team/` matches with `path=about/team/`.
  - With `.+(?<!/)`, Sylius's taxon requirement, the matcher 301s `/en_US/about/team/` to `/en_US/about/team` (experiment).
  - The generator strips lookarounds before checking requirements ([UrlGenerator L180](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Generator/UrlGenerator.php#L180)), so `trailing/` still *generates*. Path validation must reject a trailing slash.
- **Method semantics change slightly.** POSTs to unknown URLs get 405 instead of 404. GETs to POST-only paths get the CMS 404 instead of 405. Both are cosmetic.

## 3. Route conditions (the Monsieur Biz approach)

### How Monsieur Biz CMS Page 1.x does it (Sylius 1.12–1.14)

**The route** ([1.x `routing/shop.yaml`](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/1.x/src/Resources/config/routing/shop.yaml)):

- `/{slug}` with `slug: .+`, `GET` only.
- Condition: `not(context.getPathInfo() matches '^%sylius.security.new_api_route%') and context.checkPageSlug(request)`.
- The app imports it with `prefix: /{_locale}` from `config/routes/monsieurbiz_sylius_cms_page_plugin.yaml` ([README](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/1.x/README.md#installation)). That file sorts before `sylius_shop.yaml`, so the condition runs before every shop route.

**`checkPageSlug()`** is a method on a class that decorates `router.request_context`. That was the pre-6.1 way to call a service from a condition ([RequestContext.php](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/1.x/src/Routing/RequestContext.php), [services.yaml](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/1.x/src/Resources/config/services.yaml)). It:

1. returns `false` unless `$request` is the main request;
2. strips the locale code from the path, but only if the path contains the code that **`LocaleContextInterface` returns**;
3. calls `existsOneEnabledAndPublishedByChannelAndSlug(channel, localeContext->getLocaleCode(), slug, now)` ([PageSlugConditionChecker.php](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/1.x/src/Routing/PageSlugConditionChecker.php)).

The controller loads the page again, through a `findOneEnabledAndPublishedBySlugAndChannelCode` repository call in `_sylius` defaults.

### Symfony semantics (6.4)

- **The compiled matcher evaluates conditions at match time.** The dumper compiles each expression into a PHP closure ([CompiledUrlMatcherDumper L444–L446](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Matcher/Dumper/CompiledUrlMatcherDumper.php#L444-L446), L110–L126).
- **A false condition moves on to the next candidate route** (`continue` at [CompiledUrlMatcherTrait L107 and L159](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Matcher/Dumper/CompiledUrlMatcherTrait.php#L107)). So yes, later routes can still match.
- **"No extra overhead" is only true of the expression itself.** The docs say conditions are "compiled down to raw PHP… no extra overhead beyond the time it takes for the underlying PHP to execute". Here that PHP is a database query.
- **Conditions are "*not* taken into account when generating URLs"** ([docs](https://symfony.com/doc/6.4/routing.html#matching-expressions)).
- **Since 6.1, conditions have a `params` variable**, the matched route parameters, **and a `service()` function** for services tagged `routing.condition_service` or marked `#[AsRoutingConditionService]` (same docs page).
  - Wiring: `router.request_context` receives `_functions` = `router.expression_language_provider` ([routing.php L168–L191](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Bundle/FrameworkBundle/Resources/config/routing.php#L168-L191)).
  - `service` is a closure over a tagged locator ([services.php L217–L222](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Bundle/FrameworkBundle/Resources/config/services.php#L217-L222), [ExpressionLanguageProvider](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Matcher/ExpressionLanguageProvider.php)).
  - The context-decoration trick is no longer needed.
- **The `request` variable is the real request only when routing uses `matchRequest()`.** Otherwise the trait builds one from the path (`$this->request ?: $this->createRequest($pathinfo)`).
  - Sylius 1.8's storage-mode router decorator only implemented `match()`, so conditions saw a rebuilt request ([Sylius#12227](https://github.com/Sylius/Sylius/issues/12227)). That is what broke Monsieur Biz's main-request check in [CP#38](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/38).
  - The 1.14 [`LocaleStrippingRouter`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Router/LocaleStrippingRouter.php) implements `RequestMatcherInterface`.
  - Conditions that compare `request` identity remain brittle. `params` doesn't have that problem.

### Cost (experiment)

The same condition-guarded route was tested in two places, counting condition calls:

| Request | Condition before shop routes (Monsieur Biz order) | Condition at priority last |
|---|---|---|
| `/en_US/products/foo` (a page with path `products/foo` exists) | **page route wins**, hijacking the product URL (1 call) | product route (0) |
| `/en_US/login`, `/en_US/account/dashboard`, `/en_US/cart/`, `/en_US/_partial/cart/summary` | Sylius route (1 call each) | Sylius route (0) |
| `/en_US/taxons/a/b` | Sylius route (1) | Sylius route (0) |
| `/en_US/about-us` | page (1) | page (1) |
| `/en_US/about/team/` | 301 to `/en_US/about/team` (2) | 301 (2) |
| `/en_US/no-such-page`, `/api/v2/shop/nothing`, `/wp/wp-login.php` | router 404 (2 each) | router 404 (2 each) |

So with Monsieur Biz's order, nearly every main shop request pays a database query in routing, and a page can hijack a Sylius URL.

At negative priority, the condition runs only for page URLs and 404s. Each 404 costs **two** evaluations: the redirectable matcher retries with the trailing slash toggled ([CompiledUrlMatcherTrait L60–L66](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Matcher/Dumper/CompiledUrlMatcherTrait.php#L60-L66)). Unknown URLs now end as a router-level 404 rather than the soft-404 redirect in §2.

### Why Monsieur Biz users got 404s (CP#14, CP#78)

- **The condition runs too early.** It runs inside `RouterListener` (kernel.request, priority 32), *before* routing has set `_locale` and before `LocaleListener` (16) applies it ([RouterListener](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php#L177-L184), [LocaleListener](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/HttpKernel/EventListener/LocaleListener.php#L83-L92)).
- **So the locale context falls back.** Sylius's `RequestBasedLocaleContext` (priority 64) reads the main request's `_locale` attribute and throws when it is missing ([source](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/LocaleBundle/Context/RequestBasedLocaleContext.php), [context.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/Resources/config/services/context.xml#L75-L79)). The composite then falls back to `RequestHeaderBasedLocaleContext` (`Accept-Language`, priority 32, [services.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/LocaleBundle/Resources/config/services.xml#L50-L54)), then to the channel default.
- **[CP#14](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/14)** (2021): with two locales, a page 404s in one of them. It was closed after PRs [#32](https://github.com/monsieurbiz/SyliusCmsPagePlugin/pull/32) and [#33](https://github.com/monsieurbiz/SyliusCmsPagePlugin/pull/33). The workaround that ships on 1.x is `LastChanceLocaleContext` at priority -127, which scans the path for any available locale code ([source](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/1.x/src/Context/LastChanceLocaleContext.php)).
- **[CP#78](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/78)**: after Sylius 1.12.8 lowered the header context from 256 to 32 ([UPGRADE-1.12](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.12.md#upgrade-from-v1125-to-v1128)), the header context now outranks the -127 workaround. A browser that prefers `en` gets a 404 on `/fr_FR/page-test`.
  - It was fixed in [PR #87](https://github.com/monsieurbiz/SyliusCmsPagePlugin/pull/87) (merged 2025-07-22) **on 2.x only**. The fix renames the context to `FindInRequestPathLocaleContext` at priority 35, a *global* locale context that takes the first path segment equal to any locale code.
  - The 1.x branch still registers the context at -127, so the bug remains on Sylius 1.14.
- **[CP#38](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/38)** (21 comments): a user removing the locale prefix got "No route found". The cause was the `request`-identity check combined with the storage locale switcher, as above.
- **Monsieur Biz 2.x keeps the condition.** It moves the route definition into the app's YAML as `/{_locale}/{slug}` ([UPGRADE-2.0](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/2.x/UPGRADE-2.0.md)), so its ordering still depends on file names.

**Lessons for v2:**

- A routing condition must take the locale and path from **`params`**, never from Sylius's locale context.
- The channel can come from Sylius's channel context, which resolves by host ([HostnameBasedRequestResolver](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Component/Channel/Context/RequestBased/HostnameBasedRequestResolver.php)).
- The condition can't depend on the logged-in customer. The firewall runs at kernel.request priority 8, after routing ([FirewallListener](https://github.com/symfony/symfony/blob/6.4/src/Symfony/Bundle/SecurityBundle/EventListener/FirewallListener.php#L65-L71)). Audience checks therefore stay in the controller, which still answers 404.

## 4. Other approaches

### 4.1 Chain router with Symfony CMF's DynamicRouter

- **Maintenance and Symfony 6.4 support.** `symfony-cmf/routing-bundle` 3.2.0 (2026-03-24) requires `symfony/framework-bundle ^6.4 || ^7.0 || ^8.0`. `symfony-cmf/routing` 3.0.5 (2025-12-03) requires `symfony/routing ^6.0 || ^7.0 || ^8.0` ([Packagist](https://repo.packagist.org/p2/symfony-cmf/routing-bundle.json)). Both are maintained, with low activity: the [RoutingBundle repo](https://github.com/symfony-cmf/RoutingBundle) has 11 open issues and 162 stars.
- **How it works.** [`ChainRouter::doMatch()`](https://github.com/symfony-cmf/Routing/blob/3.0.5/src/ChainRouter.php) tries routers in priority order. It catches `ResourceNotFoundException` and `MethodNotAllowedException` and moves on to the next router. `chain.replace_symfony_router` defaults to `true` ([Configuration](https://github.com/symfony-cmf/RoutingBundle/blob/3.2.0/src/DependencyInjection/Configuration.php#L53)), so the bundle replaces the app's `router` service.
- **Who uses it:**
  - **Happy CMS** uses it on every line, including 0.x/1.x for Sylius 1.13/1.14 ([1.x composer.json](https://github.com/agence-adeliom/sylius-happy-cms-plugin/blob/1.x/composer.json)). It configures `router.default: 100` and `cmf_routing.dynamic_router: 50` ([cmf_routing.yaml](https://github.com/agence-adeliom/sylius-happy-cms-plugin/blob/2.3.x/config/packages/cmf_routing.yaml)) and stores one route row per translation, giving URLs like `/en_US/about/team` ([docs/routing.md](https://github.com/agence-adeliom/sylius-happy-cms-plugin/blob/2.3.x/docs/routing.md)).
  - **Sulu 2.6** prepends `router.default: 100` and `cmf_routing.dynamic_router: 20` with its content route provider ([SuluWebsiteExtension L84–L95](https://github.com/sulu/sulu/blob/2.6/src/Sulu/Bundle/WebsiteBundle/DependencyInjection/SuluWebsiteExtension.php#L84-L95)). Sulu 3's new route package still plugs into CMF ([SuluRouteBundle](https://github.com/sulu/sulu/blob/3.0/packages/route/src/Infrastructure/Symfony/HttpKernel/SuluRouteBundle.php)).
- **Assessment.** It means the same thing as "catch-all last, with an existence condition": Symfony's routes first, the database second. But a plugin would be:
  - adding a dependency;
  - replacing the app-wide `router` service, which Sylius itself decorates in storage mode (`LocaleStrippingRouter`, decoration priority 1024, [storage.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/services/integrations/locale/storage.xml));
  - keeping routes as database rows.

  It has no advantage over a negative-priority route with a condition in a single-router Sylius app.

### 4.2 A kernel.request listener before routing

- **Mechanism.** `RouterListener` skips matching when the request already has `_controller` ([L127](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php#L127)). A listener above priority 32 could look up the path and set `_controller`, `_locale` and `_route` itself.
- **It puts the database first.**
  - A page path would beat Sylius routes, so a page at `products/foo` would shadow a product.
  - The lookup would run on every request: shop, admin, API and assets served by PHP, unless the listener filters them.
- **Shopware works this way.** Its storefront `RequestTransformer` resolves the path against the `seo_url` table *before* routing, then rewrites the request to the technical path (`/navigation/{id}`) ([RequestTransformer](https://github.com/shopware/shopware/blob/trunk/src/Storefront/Framework/Routing/RequestTransformer.php), [SeoResolver](https://github.com/shopware/shopware/blob/trunk/src/Core/Content/Seo/SeoResolver.php)). A code comment there notes that an SEO URL like "admin" can resolve to a storefront route.
- **Magento uses a router list ordered by `sortOrder`:**
  - `urlrewrite` at 20, a database lookup, runs before module routes ([UrlRewrite di.xml](https://github.com/magento/magento2/blob/2.4-develop/app/code/Magento/UrlRewrite/etc/frontend/di.xml));
  - `standard` (module front names) at 30 and the `default` 404 router at 100 ([Store di.xml](https://github.com/magento/magento2/blob/2.4-develop/app/code/Magento/Store/etc/frontend/di.xml));
  - the CMS page router at 60, after module routes ([Cms di.xml](https://github.com/magento/magento2/blob/2.4-develop/app/code/Magento/Cms/etc/frontend/di.xml)).
- **Assessment.** That precedence suits a platform that owns every URL. A plugin should yield to existing routes.

### 4.3 Falling back on the router's 404

- **Mechanism.** On a miss, `RouterListener` throws `NotFoundHttpException` wrapping `ResourceNotFoundException` ([L151–L158](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php#L151-L158)). A `kernel.exception` listener could look the page up and render it.
- **The firewall never ran.** It sits at kernel.request priority 8, after the router threw. There is no security token, so the customer appears logged out and audience rules can't be evaluated.
- **The locale is missing.** `_locale` was never set, so the locale context falls back to `Accept-Language` or the channel default.
- **Rendering through a sub-request doesn't help.** The firewall skips non-main requests ([Firewall.php](https://github.com/symfony/symfony/blob/6.4/src/Symfony/Component/Security/Http/Firewall.php#L55-L57)).
- **Error-path details need care:**
  - **Logging:** `ErrorListener::logKernelException` (priority 0) logs 404s at `error` level unless an earlier listener sets a response ([ErrorListener L171–L225](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/HttpKernel/EventListener/ErrorListener.php#L171-L225)).
  - **Status code:** the response needs `allowCustomResponseCode()`, or it keeps the exception's 404.
- **Assessment.** Fragile; not recommended.
- **The catch-all doesn't break 404 handlers elsewhere.** Setono's redirect plugin acts on *any* 404 `HttpException` on the main request ([NotFoundSubscriber](https://github.com/Setono/SyliusRedirectPlugin/blob/3.x/src/EventSubscriber/NotFoundSubscriber.php)). It keeps working with a catch-all as long as the CMS controller throws `NotFoundHttpException` rather than rendering a 404 response itself.

## 5. URL shapes of existing Sylius CMS plugins

| Plugin (line) | Page URL | Mechanism | Source |
|---|---|---|---|
| BitBag v4 (`sylius ^1.13`) | `/{_locale}/page/{slug}`; slug without slashes | Fixed prefix, plain YAML route under the app's `/{_locale}` import. The docs say `/pages/{slug}`, which has drifted from the code. | [routing/shop/page.yml](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/src/Resources/config/routing/shop/page.yml), [doc/pages.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/pages.md) |
| Sylius CmsPlugin 1.2 (Sylius 2 only) | `/{_locale}/pages/{slug}`, `slug: .+(?<!/)` | Fixed prefix. [#115](https://github.com/Sylius/CmsPlugin/issues/115): generation crashed on slashes before the requirement was widened. | [config/routes/shop/page.yaml](https://github.com/Sylius/CmsPlugin/blob/1.2/config/routes/shop/page.yaml) |
| Monsieur Biz CMS Page 1.x (Sylius 1.12–1.14) | `/{_locale}/{slug}`, `slug: .+` | Root-level with a database condition. Order depends on YAML file names. | §3 |
| Monsieur Biz CMS Page 2.x (Sylius 2) | `/{_locale}/{slug}` | The same, with the route moved into app YAML | [UPGRADE-2.0](https://github.com/monsieurbiz/SyliusCmsPagePlugin/blob/2.x/UPGRADE-2.0.md) |
| Happy CMS (0.x/1.x for 1.13/1.14, 2.x for Sylius 2) | `/{locale}{parent_path}{slug}`, e.g. `/en_US/about/team` | Root-level through a CMF chain router, after Symfony's router | §4.1 |

The two root-level implementations both avoid the problem of ordering inside one route collection, in different ways: Monsieur Biz with a condition, Happy CMS with a second router. Only Monsieur Biz has public 404 bug reports about it, and they trace back to its locale handling, not to root-level routing itself.

## 6. Supporting pieces

### 6.1 Rejecting a path that a route already claims

**How.** On save or publish, and for each locale of the entry:

1. Build a fresh `RequestContext`: `GET`, the channel's hostname, `https`.
2. Match `/{locale}/{prefix?}/{path}` through the router's matcher.
3. Treat any result other than the page route as a conflict.

FrameworkBundle's `RedirectableCompiledUrlMatcher` keeps the target `_route` on trailing-slash redirects ([L33](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Bundle/FrameworkBundle/Routing/RedirectableCompiledUrlMatcher.php#L33)), so `cart` is caught too.

Experiment, against the real 1.14 shop routes plus the example app and plugin routes:

| Candidate path | Result |
|---|---|
| `about-us`, `about/team`, `products` | free |
| `products/foo` | conflict with `sylius_shop_product_show` |
| `products/foo/reviews` | conflict with `sylius_shop_product_review_index` |
| `taxons/anything/at/all` | conflict with `sylius_shop_product_index` |
| `cart` | conflict with `sylius_shop_cart_summary` (through the trailing-slash redirect) |
| `login`, `register`, `contact`, `checkout`, `checkout/address`, `order/thank-you`, `switch-locale/da_DK` | conflict with the matching Sylius route |
| `brands/acme`, `wishlist` | conflict with the app route and the later plugin route |
| `wishlist/add/5` | free for GET; the route is POST-only |
| `_partial/foo` | free, so `_`-prefixed segments should be banned by path syntax |

**Caveats:**

- **Don't mutate the shared `router.request_context`.** Use a fresh matcher or context, or restore the context in `finally`.
- **Run the check per channel hostname** if any routes have host requirements.
- **Conflicts are only known at save time.** Ship a console command for CI and deploy that re-checks every current path and reports shadowed pages, and show the same warning in the admin.
- **Add a path syntax rule:** lower-case segments, no leading `_`, no `.` or `..` segments, no trailing slash. The generator encodes `..` as `%2E%2E` (experiment), so a dot segment would produce odd URLs rather than path traversal, but it should still be rejected.

### 6.2 URL generation

- **One named route, `setono_sylius_cms_page_show`, with a `path` parameter.**
- **`UrlGenerator` keeps slashes.** `%2F` is in `$decodedChars` ([L65–L70](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/Routing/Generator/UrlGenerator.php#L65-L70)).
  - Other characters are percent-encoded. Experiment: `about/team` → `/en_US/about/team`, `om-os/æøå` → `/en_US/om-os/%C3%A6%C3%B8%C3%A5`.
  - Generation checks the requirement, so it must allow slashes. That is what Sylius CmsPlugin #115 hit.
- **`_locale` usually needn't be passed.** `LocaleListener` sets it on the router context for the current request ([L53–L80](https://github.com/symfony/symfony/blob/v6.4.50/src/Symfony/Component/HttpKernel/EventListener/LocaleListener.php#L53-L80)). Hreflang links pass `_locale` explicitly.
- **The page route generates the same way in all approaches.** Conditions don't take part in generation, and routing order doesn't affect it.
- **A Twig helper should resolve the path** for (entry, locale) and call `generate()`, so templates never build paths.

### 6.3 One prefix setting, where `''` means root-level

**It is a sensible design:**

- The loader builds `/{path}` or `/{prefix}/{path}` with the same name, parameters, priority and condition.
- Templates, hreflang and sitemap code are identical in both modes.
- The conflict check runs in both modes, but rarely fires with a prefix.
- A config value can't be used as a `%param%` in a YAML route path when it may be empty. A loader is required for priority anyway, so this costs nothing extra.

**Things to decide:**

- **Changing the value later moves every page URL.** Old paths are stored per entry and locale, so a prefix change isn't recorded as an old path. Store paths *without* the prefix. Then either let the loader also register the previous prefix as a 301 route, or document that a prefix change needs a redirect rule.
- **Validate the value's syntax.** A prefix that collides with Sylius, for example `products`, makes every single-segment page unreachable (`/en_US/products/about` → product 404). The per-path check catches this; a cache-warmup check could catch it earlier.
- **Content types with their own prefix**, such as `blog`, use the same loader mechanism at a slightly higher priority than pages (for example -999 against -1000). In root mode a page path `blog/…` then shows up as a conflict, with no special case needed.
- **Apps that disable localized URLs** (the [Sylius cookbook](https://old-docs.sylius.com/en/1.14/cookbook/shop/disabling-localised-urls.html), `locale_switcher: storage`) import the loader without `/{_locale}`. There is no `_locale` parameter then, so the condition must take the locale from Sylius's locale context. In that setup the context resolves the same way at routing and controller time, because no `_locale` attribute ever exists.

## Recommendation

Make root-level supported and tested, behind a `path_prefix` setting (`''` means root). Use this mechanism:

1. **A custom route loader** imported by the app under its shop prefix. It emits a `GET|HEAD` route with `path: .+(?<!/)` at priority -1000. Other URL-bearing content types get their own routes at a slightly higher negative priority.
2. **A condition** `service('setono_sylius_cms.page_route_checker').exists(params['_locale'], params['path'])`. It returns true for a current *or* old path of an entry in the host's channel. It never reads the locale context or the security token.
   - It memoises its result per request, as a `ResetInterface` service so worker runtimes reset it. The controller then reuses the lookup.
   - The controller does the rest: availability and audience give 404, an old path gives 301.
   - Because the route sorts last, "an old path redirects only when nothing else claims it" happens by construction.
3. **The save-time conflict check** (§6.1), plus a console check for deploys and an admin warning for shadowed pages.
4. **Index the lookup** on (channel, locale, path). Each unmatched shop URL costs one query, or two for a 404 unless memoised. A negative cache is optional.

**Default value.** The code path is the same either way, so the default is a product decision, not a technical one. Root-level as the default is defensible with the guard rails above. `pg` remains a one-line opt-out for projects that have their own catch-all routes, want path-based CDN or analytics rules, or don't want the residual risks.

## Open questions and limits

- **The experiments used the routing component, not a full app.** They ran Symfony 6.4.50 routing with Sylius's real ShopBundle YAML, not a full Sylius-Standard install. Admin, API and Payum routes were stubbed as single routes. Nothing was measured for time. "Cost" means counted condition evaluations.
- **Two behaviours come from reading source only, not from HTTP tests:** the soft-404 redirect through `NonChannelLocaleListener`, and the behaviour of Sylius's storage locale switcher with a prefix-less import.
- **Not checked:** how other popular 1.14 plugins register catch-alls (for example SEO or redirect plugins with their own routes), and whether any uses negative priorities that would interleave with ours.
- **Shopware, Sulu and Magento were read only at the level of their routing entry points.**

## Appendix: experiment setup

- **Packages:** `symfony/routing`, `config`, `yaml`, `expression-language`, `http-foundation`, `dependency-injection` and `framework-bundle`, all at 6.4.50, on PHP 8.4.
- **Shop routes:** the real `ShopBundle/Resources/config/routing.yml` from Sylius `v1.14.21`, loaded with `YamlFileLoader`, with prefix `/{_locale}` and Sylius-Standard's `_locale` requirement. Payum was skipped.
- **Load order as in Sylius-Standard:**
  1. app routes (`/{_locale}/brands/{slug}`, `/blog/{slug}`);
  2. the page loader (`config/routes/setono_sylius_cms.yaml`);
  3. admin and API stubs;
  4. Sylius shop;
  5. `config/routes.yaml` routes (`/{_locale}/wishlist` GET, `/{_locale}/wishlist/add/{id}` POST).
- **Page route:** `/{path}` with `path: .+` or `.+(?<!/)`, `GET|HEAD`, priority -1000 or 0, with or without the condition `service('cms').exists(params['_locale'], params['path'])`.
- **Matching:** routes were dumped with `CompiledUrlMatcherDumper` and matched with `RedirectableCompiledUrlMatcher::matchRequest()`. `_functions` was set on the context as FrameworkBundle does.
