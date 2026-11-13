# Sylius 1.14: constraints and extension points for a CMS plugin

Research for [#190](https://github.com/Setono/SyliusCMSPlugin/issues/190), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186).
Researched 2026-11-13. Sources were read at Sylius tag `v1.14.21` (2026-10-22, the newest 1.14 release) and `v2.3.2` (the newest 2.x release),
plus Sylius-Standard `1.14`, the Sylius docs, the Sylius Stack (Twig Hooks) docs, Symfony docs and Packagist metadata.
Every claim below links to the file or page that owns it.

## Short answer

Sylius 1.14 is a PHP ≥ 8.1, Symfony 5.4/6.4 platform with a Semantic UI + jQuery + Webpack Encore admin and shop. Its only page-level extension point is **Sylius template events** (`sylius_template_event()` plus `sylius_ui.events` config, with legacy Sonata block events wrapped inside). That config key is already deprecated in 1.14 and the whole mechanism is removed in 2.0 in favour of **Twig Hooks**. Template events pass page context unevenly. The product page passes the full `product` context. The taxon (category) page never has `taxon` in its main context; only a sub-request fragment sees it. Layout and homepage events pass nothing.

Two facts weigh most on the spec:

1. **Sylius 1.14 reaches end of security support in December 2026**, about a month after this research ([release cycle](https://docs.sylius.com/public/open-source/release-cycle.md)). A 1.14-only v2 will ship onto an unsupported platform.
2. **Everything the CMS renders in the admin or the shop, and how it hooks into pages, gets rewritten in the 2.x port.** That covers template events, Semantic UI markup, Sonata blocks and admin CRUD templates. The domain model, Doctrine mapping, ResourceBundle and grids carry over almost unchanged.

---

## 0. Platform lifecycle

| Version | Released | End of bug fixes | End of security support | Status |
|---|---|---|---|---|
| 2.3 | Sep 28, 2026 | June 2027 | December 2027 | Fully supported |
| 2.2 | Dec 17, 2025 | November 2026 | March 2027 | Fully supported |
| 1.14 (LTS) | Nov 12, 2024 | December 2025 | **December 2026** | Security fixes only |

Source: [docs.sylius.com release cycle](https://docs.sylius.com/public/open-source/release-cycle.md). The page mentions no extended or paid LTS for 1.14, and a search found no first-party offer of one. Releases still flow: v1.14.21 shipped on 2026-10-22, alongside v2.3.2 ([GitHub releases](https://github.com/Sylius/Sylius/releases)).

**Implication:** the map's "Sylius 1.14 only" premise should be re-confirmed. Any time a v2 spends on 1.14 falls after the platform's security end date.

## 1. PHP and Symfony

| | Sylius 1.14 | Sylius 2.0 | Sylius 2.3 |
|---|---|---|---|
| PHP | `^8.1` | `^8.2` | `^8.3` |
| Symfony | `^5.4.21 \|\| ^6.4` (`extra.symfony.require: ^6.4`) | `^6.4.1 \|\| ^7.1` | `^6.4.1 \|\| ^7.4 \|\| ^8.0` |
| Twig | `^2.12 \|\| ^3.3` | `^3.14` | `^3.21` |
| Doctrine ORM | `^2.13` | `^2.18` | `^2.20 \|\| ^3.6` |

Sources: [1.14 composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json), [2.0 composer.json](https://github.com/Sylius/Sylius/blob/2.0/composer.json), [2.3 composer.json](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json), [UPGRADE-2.0 "PHP support"/"Symfony support"](https://github.com/Sylius/Sylius/blob/2.0/UPGRADE-2.0.md).
The 1.14 CI matrix tests PHP 8.1, 8.2 and 8.3 against Symfony `^5.4` and `^6.4` ([matrix.json](https://github.com/Sylius/Sylius/blob/1.14/.github/workflows/matrix.json)). The docs' requirements page states PHP `^8.1`, MySQL 8.0+ and Node 18/20 ([1.14 requirements](https://old-docs.sylius.com/en/1.14/book/installation/requirements.html)).
UPGRADE-1.14 recommends `sylius/resource-bundle:~1.11.0` when running 1.14 on Symfony 5.4 ([UPGRADE-1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.14.md), section "v1.14.14 → v1.14.15").

**For the plugin:** the overlap that also eases the port is PHP ≥ 8.2 (or 8.3), Symfony 6.4, Twig 3. Supporting Symfony 5.4 buys only legacy 1.14 installs and blocks Symfony UX features that need newer components (§2.3).

## 2. Admin stack

### 2.1 What 1.14 ships

- **CSS/JS:** Semantic UI CSS `^2.2`, jQuery `^3.5`, `jquery.dirtyforms`, chart.js. There is no Stimulus or Symfony UX anywhere ([1.14 package.json](https://github.com/Sylius/Sylius/blob/v1.14.21/package.json); [composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json) has no `symfony/ux-*` or `stimulus-bundle`).
- **Build:** Webpack Encore (`symfony/webpack-encore-bundle ^1.15`, `@symfony/webpack-encore ^3.1`). It has been the default since 1.12, with Gulp still selectable via `sylius_ui.use_webpack: false` ([UPGRADE-1.12](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.12.md), "Webpack becomes our default build tool"). The admin layout prints `encore_entry_script_tags('admin-entry', null, 'admin')` ([AdminBundle Layout/_scripts.html.twig](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/AdminBundle/Resources/views/Layout/_scripts.html.twig)).
- **App-level builds:** Sylius-Standard 1.14 defines four Encore builds: `shop`, `admin`, `app.shop` and `app.admin`. The app's `assets/admin/entrypoint.js` imports plugin admin entrypoints straight from `vendor/` (Mollie and Adyen do this) ([Sylius-Standard webpack.config.js](https://github.com/Sylius/Sylius-Standard/blob/1.14/webpack.config.js), [assets/admin/entrypoint.js](https://github.com/Sylius/Sylius-Standard/blob/1.14/assets/admin/entrypoint.js), [webpack_encore.yaml](https://github.com/Sylius/Sylius-Standard/blob/1.14/config/packages/webpack_encore.yaml)). That is the de-facto convention for shipping plugin admin JS in 1.14: the host app must add an import line and rebuild.
- **Admin pages and CRUD:** ResourceBundle routes (`type: sylius.resource`, `templates: "@SyliusAdmin\\Crud"`) render generic CRUD templates. Each template fires template events named `<app>.admin.<resource>.<action>` with a fallback to `sylius.admin.<action>`, and passes the full `_context` ([Crud/update.html.twig](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/AdminBundle/Resources/views/Crud/update.html.twig)). The admin defines 118 template events in [AdminBundle events.yaml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/AdminBundle/Resources/config/app/events.yaml), including `sylius.admin.layout.javascripts`/`stylesheets` for injecting assets and `sylius.admin.product.update.tab_*` for product-edit tabs.
- **Menu:** the admin main menu dispatches `sylius.menu.admin.main` (a KnpMenu `MenuBuilderEvent`) ([MainMenuBuilder](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/AdminBundle/Menu/MainMenuBuilder.php)).
- **Grids/resources:** `sylius/grid-bundle ^1.11` and `sylius/resource-bundle ^1.11` ([composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json)). Both stay on their 1.x lines in Sylius 2.3 (`grid-bundle ^1.16`, `resource-bundle ^1.14.2`), so resource and grid definitions port well ([2.3 composer.json](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json)).
- **Deprecations already in 1.14:** many admin AJAX routes are deprecated (`sylius_admin_ajax_product_by_name_phrase`, `…taxon_by_name_phrase`, `…customer_groups_by_phrase`, …), so autocompletes built on them won't survive 2.0 ([UPGRADE-1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.14.md), "Routes deprecations").

### 2.2 Can Symfony UX (Stimulus, Twig Components, Live Components) be used inside the 1.14 admin?

Technically yes, but the plugin must bring the whole stack itself.

- The **2.x line of Symfony UX installs on Sylius 1.14.** `symfony/ux-twig-component` 2.35, `symfony/ux-live-component` 2.36 and `symfony/stimulus-bundle` 2.36 all require PHP ≥ 8.1 and accept Symfony `^5.4|^6.0|^7.0|^8.0`. They also require **Twig `^3.10.3`**, so the plugin must pin Twig 3 even though Sylius 1.14 still allows Twig 2 ([Packagist: ux-live-component](https://packagist.org/packages/symfony/ux-live-component), [ux-twig-component](https://packagist.org/packages/symfony/ux-twig-component), [stimulus-bundle](https://packagist.org/packages/symfony/stimulus-bundle)).
- The **3.x line of UX** requires PHP ≥ 8.4 and Symfony ≥ 7.4, so it cannot run on 1.14 (same Packagist pages). Sylius 2.3 accepts `^2.36 || ^3.0`, so writing against UX 2.36 APIs keeps both doors open.
- **Stimulus has no runtime in the 1.14 admin.** The plugin's admin JS entry must start `@hotwired/stimulus` and `@symfony/stimulus-bridge`, or register controllers itself, and the host app must import that entry into its `app.admin` Encore build (see §2.1). Live Components also need their JS (`@symfony/ux-live-component` assets) bundled the same way.
- **Styling:** Live and Twig Components carry no CSS framework, so components render inside Semantic UI markup. Bootstrap/Tabler-based widgets written for the 2.x admin won't look right in 1.14 without restyling.
- **AssetMapper** first shipped in Symfony 6.3 (`symfony/asset-mapper` v6.3.0, 2023-05-29; [Packagist](https://packagist.org/packages/symfony/asset-mapper)). It works only on Symfony 6.4 installs of Sylius 1.14, not 5.4. It would also run in parallel to the Encore builds Sylius itself uses. Sylius 2.x still builds with **Webpack Encore**, not AssetMapper (`symfony/webpack-encore-bundle ^2.4`, `@symfony/webpack-encore ^6` in [2.3 AdminBundle npm package.json](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/AdminBundle/Resources/npm/package.json)). Choosing AssetMapper would therefore diverge from Sylius on both lines.

Not verified: no first-party Sylius doc or plugin was found that runs Live Components inside the 1.14 admin. The claim above rests on package constraints, not a working example. The prototype ticket (#191/#196) should confirm it.

## 3. Shop template events

### 3.1 Mechanism (1.14)

- Templates call the Twig function `sylius_template_event(string|array $eventName, array $context = [])` ([TemplateEventExtension](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Twig/TemplateEventExtension.php)). An array of names merges the blocks of all of them by priority (used for "specific + generic" events such as `['sylius.shop.checkout.address.sidebar', 'sylius.shop.checkout.sidebar']`) ([TemplateBlockRegistry](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Registry/TemplateBlockRegistry.php)).
- **Blocks** are configured statically under `sylius_ui.events.<event>.blocks.<name>` with `template`, `priority`, `enabled` and a static `context` map ([UiBundle Configuration](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/DependencyInjection/Configuration.php)). The node is marked **deprecated since 1.14, removed in 2.0** in that same file.
- **Each block is rendered with `$twig->render($template, $context)`** ([TwigTemplateBlockRenderer](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Renderer/TwigTemplateBlockRenderer.php)). A block sees only what the call site passed, plus Twig globals. It does not see the calling template's other local variables.
- **Context merging:** `array_replace($block->getContext(), $eventContext)`, so the call site's context wins over the block's static context ([DefaultContextProvider](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/ContextProvider/DefaultContextProvider.php)). Services tagged `sylius.ui.template_event.context_provider` implementing `ContextProviderInterface` can add context per block. This is the official way to inject runtime data ([template customization docs](https://old-docs.sylius.com/en/1.14/customization/template.html); tag wiring in [template_event.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Resources/config/services/template_event.xml)).
- **Always-available globals in shop blocks:** `app`, `sylius` (the `ShopperContext`: `sylius.channel`, `sylius.localeCode`, `sylius.currencyCode`, `sylius.customer`), `sylius_base_locale` and `sylius_meta` ([CoreBundle config.yml `twig.globals`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/Resources/config/app/config.yml)).
- `twig.strict_variables` is `%kernel.debug%` (same file). CMS templates that read optional host context such as `product` must guard with `is defined`, or they will throw in dev.
- **Legacy Sonata block events:** many events contain a block using `@SyliusUi/Block/_legacySonataEvent.html.twig`. That block calls `sonata_block_render_event(<old event name>, _context|filter(key in sonata_block_whitelisted_variables()))` ([template](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Resources/views/Block/_legacySonataEvent.html.twig)). The whitelist comes from `sonata_block.blocks.sonata.block.service.template.settings`: `address, addresses, cart, channel, customer, form, order, order_item, orders, product, product_review, product_reviews, products, resource, resources, statistics, taxon` ([CoreBundle config.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/Resources/config/app/config.yml), [LegacySonataBlockPass](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/DependencyInjection/Compiler/LegacySonataBlockPass.php)). `LegacySonataBlockExtension` is deprecated in 1.14 ([UPGRADE-1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.14.md)), and `sonata-project/block-bundle` is dropped in 2.0 ([UPGRADE-2.0](https://github.com/Sylius/Sylius/blob/2.0/UPGRADE-2.0.md), "Removed"). **New work should not target Sonata events.**
- **Discovering events:** HTML comments around each event/block in dev, the Profiler "Template events" panel ([docs](https://old-docs.sylius.com/en/1.14/customization/template.html)) and the `sylius:debug:template-event` console command ([template_event.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Resources/config/services/template_event.xml)).

### 3.2 Event catalogue with context

Extracted from every `sylius_template_event(` call in [ShopBundle/Resources/views](https://github.com/Sylius/Sylius/tree/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/views) (98 calls). Default blocks come from the `sylius_ui.events` section of [ShopBundle config.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/app/config.yml). In the tables below, "`_context`" means the whole template context at the call site.

**Context of the two key controllers** (ResourceBundle `ResourceController`, [v1.14.2 source](https://github.com/Sylius/SyliusResourceBundle/blob/v1.14.2/src/Bundle/Controller/ResourceController.php)):
- `showAction` renders with `configuration`, `metadata`, `resource`, `<resource name>` (so `product` or `taxon`).
- `indexAction` renders with `configuration`, `metadata`, `resources`, `<plural name>` (so `products`). With a grid, this value is the grid view, and the paginated items are `resources.data`.

#### Layout: every shop page (`@SyliusShop/layout.html.twig`)

| Event | Context | Default blocks / notes |
|---|---|---|
| `sylius.shop.layout.stylesheets` | none | `_styles.html.twig`, legacy |
| `sylius.shop.layout.head` | none | legacy |
| `sylius.shop.layout.before_body` | none | legacy |
| `sylius.shop.layout.topbar` | none | currency switcher, locale switcher, security widget (each a `render(controller(...))` sub-request), legacy before/after |
| `sylius.shop.layout.header` | none | `_header.html.twig` → `sylius.shop.layout.header.grid` (logo, `…header.content`, cart) and menu |
| `sylius.shop.layout.header.grid` / `…header.content` | none | |
| `sylius.shop.layout.before_content` | none | Directly above `{% block content %}` |
| `sylius.shop.layout.after_content` | none | Directly below `{% block content %}` |
| `sylius.shop.layout.footer` → `…footer.grid` | none | plus, your_store, customer_care, payment_methods |
| `sylius.shop.layout.javascripts` | none | `_scripts.html.twig`, legacy |
| `sylius.shop.layout.after_body` | none | |

Layout Twig blocks that a CMS page template can override: `title`, `metatags` (empty by default), `stylesheets`, `top`, `header`, `content`, `footer`, `javascripts`. The 1.14 shop renders **no meta description and no Open Graph tags** anywhere; no shop template references `metaDescription` ([layout.html.twig](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/views/layout.html.twig)).

**Consequence:** layout events never receive `product` or `taxon`, even on product or taxon pages. A slot such as "above content on product pages" placed in `sylius.shop.layout.before_content` must resolve the page itself, for example from `app.request.attributes.get('_route')` and the slug, or through a context provider.

#### Homepage (`sylius_shop_homepage`, `Homepage/index.html.twig`)

| Event | Context | Default blocks |
|---|---|---|
| `sylius.shop.homepage` | none | banner (60), latest_products (50), latest_products_carousel (40), newsletter (30), products_grid (20), about_us (10) |

The default homepage is entirely a template-event composition, so a CMS can replace or disable each block by name. The product lists are `render(url('sylius_shop_partial_product_index_latest', …))` sub-requests.

#### Product page (`sylius_shop_product_show`, `/products/{slug}`, `Product/show.html.twig`)

The route loads the product with `findOneByChannelAndSlug(channel, localeCode, slug)` ([routing/product.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/routing/product.yml)).
Page context: `configuration`, `metadata`, `resource`, `product`.

| Event | Context | Default blocks (priority) |
|---|---|---|
| `sylius.shop.product.show.left_sidebar` | `_context` (has `product`) | legacy `before_images` (15), `_images.html.twig` (10), legacy `after_images` (5) |
| `sylius.shop.product.show.right_sidebar` | `_context` | header (50), legacy `after_product_header` (45), reviews (40), legacy `after_reviews` (35), price_widget (30), legacy `after_price` (25), short_description (20), legacy `after_description` (15), variant_selection (10), legacy `after_add_to_cart` (5) |
| `sylius.shop.product.show.content` | `_context` | legacy `before_tabs` (25), tabs (20), legacy `before_associations` (15), associations (10) |
| `sylius.shop.product.show.tab_details` | `_context` (inherited through includes, has `product`) | legacy (15), description (10) |
| `sylius.shop.product.show.tab_attributes` | `_context` | legacy (15), list (10) |
| `sylius.shop.product.show.tab_reviews` | `_context` | legacy (35), latest (30), view_more (20), create (10) |
| `sylius.shop.product.show.before_thumbnails` | `{product}` | legacy |
| `sylius.shop.product.show.before_add_to_cart` | `{product, order_item}` | |
| `sylius.shop.product.show.add_to_cart_form` | `{product, order_item, form}` | |

The product page is the best-served page: three top-level positions (left column, right column, full-width below) receive the full page context, plus finer positions inside the tabs and the add-to-cart box. There is **no `variant`** in context: variant selection is client-side.

#### Taxon / category page (`sylius_shop_product_index`, `/taxons/{slug}`, `Product/index.html.twig`)

The route is a **product grid**, not a taxon show (`sylius.controller.product::indexAction`, grid `sylius_shop_product`; [routing/product.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/routing/product.yml)).
Main page context: `configuration`, `metadata`, `resources`, `products`. **No `taxon`.** The taxon is loaded only inside two fragment sub-requests, `render(url('sylius_shop_partial_taxon_show_by_slug', {slug, template}))` ([Product/Index/_header.html.twig](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/views/Product/Index/_header.html.twig), [_sidebar.html.twig](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/views/Product/Index/_sidebar.html.twig), [routing/partial/taxon.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/routing/partial/taxon.yml)).

| Event | Where | Context | Default blocks |
|---|---|---|---|
| `sylius.shop.taxon.header` | fragment sub-request | `{taxon}` | breadcrumb (20), legacy `taxon.header.before` (15), `Taxon/Header/_content` (10), legacy `taxon.header.after` (5) |
| `sylius.shop.product.index.before_vertical_menu` | fragment sub-request | `{taxon}` | |
| `sylius.shop.product.index.after_vertical_menu` | fragment sub-request | `{taxon}` | |
| `sylius.shop.product.index.search` | main request | `_context` (`resources`, `products`, no `taxon`) | legacy before (35), search (30), legacy after (25), pagination (20), sorting (10) |
| `sylius.shop.product.index.before_list` | main request | `{products: resources.data}` | |
| `sylius.shop.product.index.before_pagination` | main request | `{products: resources.data}` | |
| `sylius.shop.product.index.box` | each product card | `{product}` | **Not taxon-specific.** `Product/_box.html.twig` is also used by homepage lists and carousels, associations and the review box. |

Gotchas for "slots on category pages":
- `taxon` exists only in the header and sidebar fragments. Inside them, `app.request` is the fragment's sub-request (route `sylius_shop_partial_taxon_show_by_slug`), not the page request.
- To place taxon-aware content above or below the product grid (`before_list`, `before_pagination`), the CMS must resolve the taxon itself. One way is to read the main request's `slug`, then call `TaxonRepository::findOneBySlug(slug, locale)` as the partial route does. Another is a context provider, or overriding `Product/index.html.twig` to pass `taxon` in. Overriding the template is a template override, not an extension point, and the app has to own it.

#### Other shop pages (call site → context)

| Page | Events (context) |
|---|---|
| Cart (`Cart/summary.html.twig`) | `sylius.shop.cart.header` `{header, cart}`, `.items` `{cart, form}`, `.summary` `{cart}`, `.suggestions` `{cart}`, `.summary.items` `{cart, form}`, `.coupon` `{cart, form, main_form}`, `.summary.totals` `{cart}` |
| Cart widget (header, via `sylius_shop_partial_cart_summary` sub-request) | `sylius.shop.cart.widget.button` `{cart}`, `.popup` `{cart}` |
| Checkout address / shipping / payment | `…<step>.steps`+`sylius.shop.checkout.steps` (`_context` + `active`, `orderTotal`), `…<step>.before_form` `{order}`, `…<step>.before_navigation` `{order}`, `…<step>.sidebar`+`sylius.shop.checkout.sidebar` (`_context`), `checkout.address.billing_address_form`/`shipping_address_form` `{order}`, `select_shipping.choice_item_content`/`choice_item_extra` `{method, fee}`, `select_payment.choice_item_content` `{method}`, `sylius.shop.checkout.header` (`_context`) |
| Checkout complete | `…complete.steps` (`_context` + active), `…after_content_header` `{order}`, `…summary` (`_context`), `…before_navigation` `{order}` |
| Thank-you | `sylius.shop.order.thank_you.after_message` `{order}` |
| Account | `account.layout.menu`/`before_subcontent`/`after_subcontent` (none); `dashboard.after_content_header`/`after_information` `{customer}`; `profile.update.*` `{customer, form}`; `change_password.*` `{form}`; `address_book.*` `{address}`/`{addresses}`; `order.index.subcontent`/`order.show.subcontent` (`_context`); `order.index.header.content` `{orders}` |
| Login / register / password reset | `login.after_content_header` (none), `login.main_column`/`register_column` (`_context`), `login.form` `{form}`; `register.after_content_header`/`before_form` (none), `register.form` `{form}`; `request_password_reset_token.*` (none / `{form}`) |
| Contact | `contact.request.after_content_header` (none), `contact.request.form` `{form}` |
| Product reviews | `product_review.index.product_box`/`list` (`_context`), `product_review.create.product_box` (`_context`), `.before_form` `{product_review}`, `.form` `{product_review, form}` |

The shop has **no template events on error pages (404/500)**; 2.x adds hooks there (`twig_hooks/errors/*.yaml` in 2.3).

### 3.3 How a plugin adds its own positions and content

1. **Add a block to an existing event.** Ship `sylius_ui.events.<event>.blocks.<name>` config with `template`/`priority`. Prepending the config from the bundle extension or documenting it for the app both work. This is the officially documented route ([docs](https://old-docs.sylius.com/en/1.14/customization/template.html)), though the config key emits a 1.14 deprecation.
2. **Inject runtime data** through a `ContextProviderInterface` service tagged `sylius.ui.template_event.context_provider`. The interface is deprecated in 1.14 ([UPGRADE-1.14](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.14.md)) but there is no replacement before 2.0.
3. **Define new events** by calling `sylius_template_event('setono_cms.<…>', context)` in the plugin's own templates. Any name works; the registry needs no declaration. Projects then attach blocks to it.
4. **New positions inside Sylius pages** require overriding the Sylius template (`templates/bundles/SyliusShopBundle/…` or a `sylius/theme-bundle` theme). That is app-owned code, so the CMS should document it, not ship it.

Because blocks are compiled into the container ([TemplateBlockRegistry](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/UiBundle/Registry/TemplateBlockRegistry.php)), **merchant-managed slots cannot be created at runtime as blocks**. The workable pattern is one generic CMS block per supported event. That block looks up the published content for (event, channel, locale, page) at render time and renders it with the event's context.

## 4. Channel, locale and customer group APIs

- **Channel:** `ChannelContextInterface::getChannel()` (`sylius.context.channel`). It is a composite, with services tagged `sylius.context.channel`: request-based (hostname resolver, extensible via `sylius.context.channel.request_based.resolver`), fake channel (priority 128, dev) and single channel (priority -128). Results are cached per request ([Component/Channel/Context](https://github.com/Sylius/Sylius/tree/v1.14.21/src/Sylius/Component/Channel/Context), [ChannelBundle services](https://github.com/Sylius/Sylius/tree/v1.14.21/src/Sylius/Bundle/ChannelBundle/Resources/config)).
- **Locale:** `LocaleContextInterface::getLocaleCode()` (`sylius.context.locale`). It is a composite of request-based (`_locale` route attribute, priority 64), request-header-based (32) and provider-based (channel default, -128) contexts ([LocaleBundle config](https://github.com/Sylius/Sylius/tree/v1.14.21/src/Sylius/Bundle/LocaleBundle/Resources/config)). Shop routes live under the app's `/{_locale}` prefix, so CMS shop routes must be imported under the same prefix. The shop firewall regex is `^/(?!admin|api/.*|api$|media/.*)[^/]++` (`sylius.security.shop_regex` in [ShopBundle config.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/config/app/config.yml)).
- **Shopper context:** `ShopperContextInterface` bundles channel, currency, locale and customer, and is exposed in Twig as `sylius` ([Component/Core/Context](https://github.com/Sylius/Sylius/tree/v1.14.21/src/Sylius/Component/Core/Context)).
- **Customer and group:** `CustomerContextInterface::getCustomer(): ?CustomerInterface` reads the logged-in shop user from the security token ([CoreBundle/Context/CustomerContext](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/Context/CustomerContext.php)). A customer has **at most one group**: `CustomerInterface::getGroup(): ?CustomerGroupInterface` ([CustomerInterface](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Component/Customer/Model/CustomerInterface.php)). Group targeting is therefore a single-value match, and it needs the session (see §7).
- **Translations:** ResourceBundle's translatable model (`TranslatableInterface`/`TranslatableTrait` with current and fallback locale) is the same mechanism Sylius uses for products and taxons. It is unchanged in 2.x, which still uses `sylius/resource-bundle` 1.x.

## 5. File storage and Liip Imagine

- **Flysystem is the default** since 1.12. `sylius_core.filesystem.adapter` accepts `default` (= Flysystem), `flysystem` or `gaufrette` ([CoreBundle Configuration](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/DependencyInjection/Configuration.php), alias wiring in [SyliusCoreExtension](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/DependencyInjection/SyliusCoreExtension.php)). Gaufrette needs an explicit opt-in ([UPGRADE-1.12](https://github.com/Sylius/Sylius/blob/v1.14.21/UPGRADE-1.12.md) items 9–10).
- Sylius' own abstraction is `Sylius\Component\Core\Filesystem\Adapter\FilesystemAdapterInterface` (`has`, `write`, `delete`), aliased `sylius.adapter.filesystem.default`. The Flysystem storage is `sylius.storage` (local adapter, `directory: '%sylius_core.images_dir%'`, public visibility) ([CoreBundle config.yml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/Resources/config/app/config.yml)). Packages: `league/flysystem-bundle ^2.4`, and Gaufrette is still required (`knplabs/gaufrette ^0.10 || ^0.11`, `knp-gaufrette-bundle ^0.7 || ^0.8`) ([composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json)).
- **Liip Imagine** `^2.10`. The default loader is a **local filesystem** loader rooted at `%sylius_core.images_dir%`, and the default resolver is `web_path` with cache prefix `media/cache`. Core filter sets are `sylius_original`, `sylius_small`, `sylius_medium` and `sylius_large` (same config.yml). The Shop and Admin bundles add `sylius_shop_product_{tiny_,small_,large_}thumbnail` and `sylius_admin_product_*` sets in their own `config.yml`. A plugin can add its own filter sets. Remote storage (S3 and similar) requires reconfiguring both the Flysystem storage and the Liip loader/resolver in the app; Sylius does not wire that for you.
- **2.x:** Flysystem bundle `^3.x`. Gaufrette is still listed in 2.0 but **gone from 2.3's composer.json**. The Liip default loader and resolver were renamed from `default` to `sylius_image` ([UPGRADE-2.0](https://github.com/Sylius/Sylius/blob/2.0/UPGRADE-2.0.md), "LiipImagineBundle Default Resolver and Loader"). A media library should therefore depend on Flysystem `FilesystemOperator` directly, or on its own storage service, with its own Liip loader and resolver names, not Sylius' `default` names.

## 6. API Platform

- 1.14: **`api-platform/core ^2.7.10`** ([composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json)). Sylius resources are declared in **legacy 2.x XML metadata** (`<collectionOperations>/<itemOperations>`) under `ApiBundle/Resources/config/api_resources/` ([Channel.xml](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ApiBundle/Resources/config/api_resources/Channel.xml)). CI pins `^2.7.10` ([matrix.json](https://github.com/Sylius/Sylius/blob/1.14/.github/workflows/matrix.json)).
- 2.0: `api-platform/symfony ^4.0.3`. 2.3: `api-platform/symfony ^4.3.17 || ^5.0` ([2.0](https://github.com/Sylius/Sylius/blob/2.0/composer.json), [2.3](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json) composer.json). The metadata format and the state provider/processor model differ completely from 2.7 legacy metadata.
- **Implication:** v2.0 ships no API (per #186), which is fortunate. An API written against 1.14's API Platform 2.7 would be rewritten for 2.x. "API-ready" should mean a model that maps cleanly to API Platform 3/4 resources (DTO-friendly, no Twig in the model, stable codes and IRIs), not 2.7 XML.

## 7. HTTP caching

- Sylius 1.14 ships **no HTTP-cache integration**. No `Cache-Control`, `setSharedMaxAge`, ESI or `fos_http_cache` appears in Shop, Core or UI bundle code or config, and FOSHttpCacheBundle is not a dependency ([composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json); a code search of the 1.14 Shop, Core and Ui bundles returned nothing).
- **Every shop page is session-bound by default.** The layout renders sub-requests for the cart widget (`sylius_shop_partial_cart_summary`), the security widget, the currency and locale switchers and the taxon menu on every page ([Layout/Header/_cart.html.twig](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/views/Layout/Header/_cart.html.twig), [Layout/Topbar](https://github.com/Sylius/Sylius/tree/v1.14.21/src/Sylius/Bundle/ShopBundle/Resources/views/Layout/Topbar)). The product page also renders the add-to-cart form, and [`AddToCartType`](https://github.com/Sylius/Sylius/blob/v1.14.21/src/Sylius/Bundle/CoreBundle/Form/Type/Order/AddToCartType.php) does not disable CSRF. Symfony: "Whenever the session is started during a request, Symfony turns the response into a private non-cacheable response". The opt-out is the `AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER` header, and per-user-group caching is delegated to FOSHttpCacheBundle ([Symfony 6.4 HTTP cache](https://symfony.com/doc/6.4/http_cache.html#http-caching-and-user-sessions)).
- The fragments use `render()` (inline), so a project could switch them to `render_esi()` behind Varnish or Symfony HttpCache. That is an app-level template override, not something Sylius supports out of the box.
- **Implication for the CMS:** full-page HTTP caching of shop pages is not something the plugin can rely on. CMS-owned pages that skip cart and session fragments could be made cacheable. Targeting by customer group, and anything else that reads the customer, forces session-dependent rendering. Cache invalidation on publish therefore matters mostly for CMS-owned pages, ESI fragments in projects that opt in, and application-level caches (Twig fragment or Symfony Cache pools). This feeds the "Storefront rendering and caching" item on #186.

## 8. Existing sitemap and SEO plugins

| Package | Latest | Sylius support | What it gives a CMS |
|---|---|---|---|
| [`stefandoorn/sitemap-plugin`](https://packagist.org/packages/stefandoorn/sitemap-plugin) (~1.15M installs) | v2.4.3 (2025-03-12) for 1.9–1.14, v3.0.1 (2026-10-07) for `^2.0` | both lines | **Extension point:** implement `UrlProviderInterface` and tag the service `sylius.sitemap_provider` ([README v2.4.3](https://github.com/stefandoorn/sitemap-plugin/blob/v2.4.3/README.md#add-own-provider)). Ships product, taxon and static providers with hreflang alternates. The CMS can ship an optional provider for pages and collections. |
| [`dedi/sylius-seo-plugin`](https://packagist.org/packages/dedi/sylius-seo-plugin) | 3.x supports `~1.11`–`~1.14` (v3.2.0, 2026-10-07); 4.x supports 2.0–2.3 | both lines | Meta tags, Open Graph, noindex rules, canonical and hreflang, Breadcrumb and Product JSON-LD for channels, products, taxons and arbitrary URLs ([README](https://github.com/dediagency/sylius-seo-plugin)) |
| [`setono/sylius-seo-plugin`](https://packagist.org/packages/setono/sylius-seo-plugin) | v1.0.0 (2025-12-15) for Sylius `^1.0`; v2.0.0-alpha.1 for `^2.0` | both lines | Schema.org data generation, robots.txt management ([README v1.0.0](https://github.com/Setono/sylius-seo-plugin/blob/v1.0.0/README.md)) |
| `joppedc/sylius-better-seo-plugin` | v1.0.3 (2023) | `^1.4` | Stale; skip |
| `stefandoorn/sylius-seo-url-plugin` | v1.0.0-beta (2021) | `^1.3` | Stale; skip |

Core Sylius 1.14 renders no meta description or OG tags (§3.2). A CMS that owns pages must emit its own `<title>` and `metatags`, or integrate with one of the plugins above, and it cannot assume either plugin is installed.

## 9. Sylius 2.x: what changes and what it costs the port

| Area | 1.14 | 2.x (2.3) | Port cost for the CMS |
|---|---|---|---|
| PHP / Symfony | 8.1+ / 5.4, 6.4 | 8.3+ / 6.4, 7.4, 8.0 | Low if v2 targets PHP 8.2+/Symfony 6.4 |
| Page extension | `sylius_template_event` + `sylius_ui.events` (deprecated in 1.14), Sonata legacy | **Twig Hooks** (`sylius/twig-hooks`). `{% hook 'name' with {…} %}` and `sylius_twig_hooks.hooks.<hook>.<hookable>` config. Hookables are templates or Twig Components, and data is read via `hookable_metadata.context.<key>` or `get_hookable_context()`. The UiBundle template-event classes and `TemplateEventExtension` were removed ([UPGRADE-2.0](https://github.com/Sylius/Sylius/blob/2.0/UPGRADE-2.0.md), "Classes and Interfaces → Removed"; [Twig Hooks docs](https://github.com/Sylius/Stack/blob/main/docs/twig-hooks/passing-data-to-your-hookables.md)) | **High.** Every slot binding (event name, context keys) must be remapped. Isolate it behind one "slot position" abstraction. |
| Shop event names | e.g. `sylius.shop.product.show.right_sidebar` | e.g. `sylius_shop.product.show.content.info.summary`. The 2.3 product page has 22 nested hooks under `sylius_shop.product.show`, entered with `{ product, configuration }`; the product index has 12 under `sylius_shop.product.index`, entered with `{ products, resources }` ([2.3 product/show.yaml](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/product/show.yaml), [product/index.yaml](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/product/index.yaml), [product/show.html.twig](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/product/show.html.twig)) | No 1:1 name mapping; positions differ. `product` survives as a context key. On 2.3 category pages `taxon` is still not in the hook context; Twig Components such as `sylius_shop:product:show:header` resolve it. |
| Shop CSS | Semantic UI, jQuery | Bootstrap 5, Stimulus, Live Components | All shop block templates restyled |
| Admin UI | Semantic UI 2, jQuery, Encore 3, generic `@SyliusAdmin/Crud` + 118 template events | Tabler 1.4 + Bootstrap 5.3, Stimulus via `@symfony/stimulus-bridge`, Live Components (40 admin Twig components in 2.3), Encore 6, Twig Hooks per resource ([2.3 AdminBundle npm package.json](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/AdminBundle/Resources/npm/package.json)) | **High** for templates and JS. Lower if the 1.14 admin editor is built as a self-contained Stimulus or Live Component app with its own CSS, which carries over. |
| Symfony UX | none (UX 2.x installable by plugin) | `ux-twig-component`, `ux-live-component`, `stimulus-bundle`, `ux-autocomplete`, `ux-icons` `^2.36 \|\| ^3.0` | Building the editor on UX 2.36 in 1.14 aligns with 2.x |
| Asset build | Encore (Gulp optional) | Encore (no AssetMapper) | Stay on Encore |
| Resources / grids | resource-bundle 1.11+, grid-bundle 1.11+ | resource-bundle 1.14.2+, grid-bundle 1.16+ | **Low.** Same major. |
| Sonata block bundle | required, legacy events | removed | Don't use it |
| Storage | Flysystem default, Gaufrette optional | Flysystem 3; Gaufrette dropped by 2.3; Liip names `sylius_image` | Low if on Flysystem with own names |
| API Platform | 2.7 legacy XML metadata | 4.x (5.x allowed in 2.3) | High for any API code; none ships in v2.0 |
| State machine | `sylius_state_machine_abstraction`, default adapter `winzou_state_machine` | default adapter changed to `symfony_workflow`, winzou optional ([UPGRADE-2.0](https://github.com/Sylius/Sylius/blob/2.0/UPGRADE-2.0.md), "Sylius State Machine Abstraction") | Use the abstraction or Symfony Workflow for publishing workflow |
| Admin AJAX routes | many deprecated in 1.14 | replaced by `ux-autocomplete` | Don't build on them |
| Error pages | no events | hooks for 403/404/500 | 2.x adds positions |

**Biggest porting risks, ranked:**
1. **Slot placement layer.** Template events → Twig Hooks: different names, nesting and context-passing model, and the config key is deprecated even in 1.14. Mitigation: define the CMS's own stable slot identifiers with per-platform adapters (a 1.14 block or context provider, a 2.x hookable) and keep `sylius.shop.*` names out of stored content.
2. **Admin UI and editor.** Semantic UI/jQuery → Tabler/Bootstrap + Stimulus/Live. Mitigation: build the visual builder as a self-contained Stimulus/Live Component app with scoped CSS on UX 2.36. Its host app must import the plugin entry into its `app.admin` Encore build in 1.14.
3. **Shop block templates.** Semantic UI markup is rewritten for Bootstrap 5. Mitigation: keep block templates small and theme-overridable, and avoid depending on Sylius shop partials.
4. **Platform end of life.** 1.14 security support ends December 2026, so the window in which a 1.14-only v2 is useful is short.
5. **API.** The API Platform 2.7 → 4 jump is total, but v2.0 ships no API, so the risk is only "don't model around 2.7".

## 10. Not verified / open

- No first-party example was found of Symfony UX Live Components running inside the 1.14 Semantic UI admin. The claim is based on Composer constraints only; prototype it in #191/#196.
- Whether Twig Hooks context is inherited by nested hooks in all cases. The [Twig Hooks docs](https://github.com/Sylius/Stack/blob/main/docs/twig-hooks/passing-data-to-your-hookables.md) describe passing data "down to children elements". The 2.3 configs re-pass `product` explicitly via `props` for components.
- Paid or extended 1.14 support beyond December 2026: none found from Sylius; ask Sylius directly if it matters.
- Line-level behaviour of the default `sylius_shop_product` grid (filters, sorting by taxon) was not studied, because it doesn't affect slot context.
