# What existing Sylius CMS plugins do well and badly

Research for [#189](https://github.com/Setono/SyliusCMSPlugin/issues/189), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186). Gathered 2026-11-13.

**Sources.** Each plugin's repo (README, docs folder, source), Packagist metadata (`repo.packagist.org/p2/<package>.json`) for versions and Sylius constraints, and the plugins' GitHub issues. Issue numbers link to the issue tracker of the plugin they belong to. Setono v1 source was not studied; only its open issues were mined.

## Short answer

The field splits into two camps. **BitBag / Sylius CMS** has a developer-centric model: coded blocks and pages, placed in templates with Twig functions, with simple display rules (channel, product, taxon). **Monsieur Biz** (and the smaller Happy CMS and Sherlockode) put a block-based editor in the hands of merchants, but mainly for standalone pages and homepages. No plugin combines a good editor with slots in existing shop pages, host-page context and rich targeting.

Users struggle most with:

- an editor that loses content or can't reorder elements;
- weak WYSIWYGs;
- painful entity extension;
- localized-URL and channel quirks;
- hard-wired media storage;
- no draft or versioning workflow.

For Sylius 1.14 the situation is bleak. Every active vendor has moved development to Sylius 2, and the 1.x lines are in maintenance or marked unmaintained.

## Plugins surveyed

| Plugin | Package | Latest | Sylius 1.14 line | Status on 1.x |
|---|---|---|---|---|
| BitBag CMS | `bitbag/cms-plugin` | v4.4 (2025-03-05), `sylius/sylius ^1.13` | v4.x | README: "supports Sylius 1.x versions only. The Sylius 2.x support is currently handled by Sylius team". No release since 2025-03. |
| Sylius CMS (fork of BitBag) | `sylius/cms-plugin` | v1.2.0 (2026-11-03), v2.0.0-ALPHA.1 (2026-10-26) | **none**, every release needs `sylius/sylius ^2.0` | n/a |
| Monsieur Biz Rich Editor | `monsieurbiz/sylius-rich-editor-plugin` | v3.2.0 (Sylius 2) | 2.11.x (`>=1.12 <2.0`, last 2.11.9 on 2025-05-07) | maintenance branch `2.0` |
| Monsieur Biz CMS Page | `monsieurbiz/sylius-cms-page-plugin` | v2.2.0 (Sylius 2) | 1.2.x (`>=1.12 <2.0`, last 1.2.3 on 2025-03-04) | branch `1.x` |
| Monsieur Biz Menu | `monsieurbiz/sylius-menu-plugin` | v2.0.5 (Sylius 2) | 1.5.x (`>=1.13 <2.0`, last 1.5.4 on 2025-08-01) | branch `1.x` |
| Monsieur Biz CMS Block | `monsieurbiz/sylius-cms-block-plugin` | v2.0.5 (Sylius 2) | 1.1.x (`>=1.12 <2.0`) | branch `1.x` |
| Monsieur Biz Homepage | `monsieurbiz/sylius-homepage-plugin` | v2.1.0 (Sylius 2) | 1.x branch | branch `1.x` |
| Happy CMS (Adeliom) | `agence-adeliom/sylius-happy-cms-plugin` | v2.4.0 (Sylius ^2.3) | 1.14.x | README versions table: "Maintained: No" |
| Sherlockode Advanced Content | `sherlockode/sylius-advanced-content-plugin` | v1.0.1 (Sylius ^2.0) | v0.4.0 (`^1.10`, 2025-05-05) | no further 1.x releases |
| Netgen Layouts for Sylius | `netgen/layouts-sylius` | 2.0.3 (Sylius ^2.2) | 1.4.x (`^1.11.6`, last 1.4.11 on 2025-07-31) | maintenance |

Version data comes from Packagist, e.g. <https://repo.packagist.org/p2/bitbag/cms-plugin.json>. The [Sylius Store/Add-ons](https://addons.sylius.com/?s=cms) search for "cms" lists only the official "CMS Plugin by Sylius".

By Packagist downloads, BitBag (1.2M) and Rich Editor (478K) dominate. Next come Monsieur Biz CMS Page (161K), Sylius CMS (138K) and Menu (97K). Happy CMS (2.3K), Sherlockode (1.9K) and Netgen Layouts are niche.

Not studied in depth: the blog plugins (`odiseoteam/sylius-blog-plugin` v1.4.3 for `^1.12`, `monsieurbiz/sylius-blog-plugin`), banner plugins (`odiseoteam/sylius-banner-plugin`) and `softspring/cms-sylius-bundle`.

---

## BitBag SyliusCmsPlugin (v4, Sylius 1.12–1.14)

Repo: <https://github.com/BitBagCommerce/SyliusCmsPlugin> (docs in [`doc/`](https://github.com/BitBagCommerce/SyliusCmsPlugin/tree/master/doc)).

### Content model

The plugin has five resources, each with a code and translations:

- **Blocks**: content hard-placed in templates.
- **Pages**: slug, content, `nameWhenLinked` and `descriptionWhenLinked` for teasers.
- **Sections**: a grouping of pages and blocks, e.g. "blog".
- **Media**: image, video and file.
- **FAQs**.

Blocks and pages associate with channels, products and sections. These are the CSV import columns in [importing-resources.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/importing-resources.md).

Content is one HTML field per locale. There is no structured block or element model.

### Admin UX

CKEditor 4 comes in through FOSCKEditorBundle, and uploading an image in it creates a Media resource ([wysiwyg.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/wysiwyg.md)).

Editors can type a whitelist of Twig calls, `{{ bitbag_cms_render_block('x') }}` and `{{ bitbag_cms_render_media('y') }}`, into the WYSIWYG. They are parsed out of the HTML with a regex ([twig-functions-in-admin.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/twig-functions-in-admin.md)).

Resources can be imported from CSV in the admin or with `bitbag:import:csv`.

### Getting content into shop pages

Developers edit templates:

- `bitbag_cms_render_block('code')` renders a block.
- `render(path('bitbag_sylius_cms_plugin_shop_block_index_by_product_code', …))` and `…_by_section_code` render blocks through Symfony sub-requests.
- `bitbag_cms_render_product_pages(product)` renders a product's pages.
- `bitbag_cms_render_link_for_code` renders a link to a page.

Pages live at `/{_locale}/pages/{slug}`. Sources: [blocks.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/blocks.md), [pages.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/pages.md) and [sections.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/sections.md).

### Localization and channels

Fields are translated per Sylius locale and each resource has a channel association. URLs are localised by default. Turning that off means re-importing the vendor routing without the prefix ([customization.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/customization.md)).

### Extending

The plugin documents only generic mechanisms: template overrides, service decoration, form type extensions, Sylius resource model overrides and tagged CSV importers ([customization.md](https://github.com/BitBagCommerce/SyliusCmsPlugin/blob/master/doc/customization.md)).

### Pain points from issues

- **Extending entities is the top support burden.** See [#36](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/36) (10 comments), [#324](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/324) (12), [#164](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/164), [#307](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/307), [#186](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/186), [#266](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/266), [#425](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/425) and [#421](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/421). Validation overrides cause the same trouble: [#261](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/261) and [#399](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/399).
- **The WYSIWYG is fragile.**
  - Long CKEditor threads: [#411](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/411) and [#120](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/120).
  - CKEditor 4 reached end of life: [#485](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/485).
  - Toolbar and asset bugs: [#484](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/484), [#326](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/326), [#253](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/253) and [#419](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/419).
  - Users asked for the editor to be optional: [#39](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/39).
- **Twig typed into the WYSIWYG breaks.** The regex fails on two calls in one field ([#325](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/325)), and other reports show parser errors ([#460](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/460), [#544](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/544)). Editors still want to call templates from content ([#483](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/483)).
- **Channel-specific content is missing.** Users want different content per channel under the same slug, e.g. channel-specific terms and conditions ([#549](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/549)). Related: [#136](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/136), and a page for another channel showing up in the sitemap ([#206](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/206)).
- **Media uploads and S3 cause problems.** See [#285](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/285), [#431](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/431), [#478](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/478), [#262](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/262) and [#469](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/469).
- **Performance.** Every block render issues an extra query ([#223](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/223)), and users asked for lazy Twig extensions ([#282](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/282)).
- **Installing and upgrading is churny.** [#210](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/210) has 40 comments. See also [#455](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/455), [#426](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/426), [#436](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/436) and [#497](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/497) (migrations shipped in the plugin).
- **Missing features.** Users asked for:
  - sorting blocks within a section ([#113](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/113));
  - theming support ([#129](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/129), [#248](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/248));
  - sitemap support ([#41](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/41), [#473](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/473));
  - a REST API ([#20](https://github.com/BitBagCommerce/SyliusCmsPlugin/issues/20)).

## Sylius CmsPlugin (official, BitBag fork, Sylius 2 only)

Repo: <https://github.com/Sylius/CmsPlugin>. The README points to <https://docs.sylius.com/cms-plugin>, but an anonymous fetch redirected to a GitBook visitor-auth login, so the findings below come from source. The README also says the plugin "enforces telemetry data collection".

### Content model

Field lists come from [`src/Entity`](https://github.com/Sylius/CmsPlugin/tree/1.2/src/Entity) on branch `1.2`.

- **`Page`**: code, name, enabled, `publishAt`, channels, collections, static template, timestamps. A teaser (title, content, image) comes from `TeaserTrait`.
- **`Block`**: code, name, template, channels, collections, and display rules for `products`, `taxons` and `productsInTaxons`.
- **`Collection`**: typed as pages, blocks or media. It replaces BitBag's sections.
- **`Media`**: type, path, dimensions, channels.
- **`Template`**: a reusable list of content elements.
- **Content elements**: page and block bodies are lists of **content elements**, each stored as a `ContentConfiguration` row with `type`, a `configuration` array and a **`locale`**. Every locale therefore has its own element list.

The built-in element types are:

- heading;
- textarea (WYSIWYG);
- single and multiple media;
- products carousel and grid, each by manual pick or by taxon;
- pages collection;
- spacer;
- taxons list.

Source: [`src/Form/Type/ContentElements`](https://github.com/Sylius/CmsPlugin/tree/1.2/src/Form/Type/ContentElements).

### Admin UX

The editor is built on Symfony UX Live Components (`Twig/Component/*/FormComponent.php`) and has a preview. The WYSIWYG is Trix. Quill can be plugged in through a `WysiwygStrategy` ([#125](https://github.com/Sylius/CmsPlugin/issues/125), merged in [PR #151](https://github.com/Sylius/CmsPlugin/pull/151)).

The 2.0 alpha redesigns every form with a common layout: tabs, a locale switcher, the content editor and settings cards ([UPGRADE-2.0.md](https://github.com/Sylius/CmsPlugin/blob/2.0/UPGRADE-2.0.md)).

### Getting content into shop pages

The Twig functions are `sylius_cms_render_block(code, template, context)`, `sylius_cms_render_content`, `sylius_cms_render_media`, `sylius_cms_render_page_link` and `sylius_cms_get_page_url`. There is also a `sylius_cms:shop:render:block` Twig component ([#76](https://github.com/Sylius/CmsPlugin/issues/76)).

The block `context` may be a product or taxon. It is only used to check the block's display rules ([`RenderBlockRuntime`](https://github.com/Sylius/CmsPlugin/blob/1.2/src/Twig/Runtime/RenderBlockRuntime.php)).

Content-element renderers get **only** their `ContentConfiguration`, never the host page's context ([`HeadingContentElementRenderer`](https://github.com/Sylius/CmsPlugin/blob/1.2/src/Renderer/ContentElement/HeadingContentElementRenderer.php)). An API Platform config ships for the shop API (`config/api_platform`; see [#142](https://github.com/Sylius/CmsPlugin/issues/142)).

### Extending

A custom element is a form type with a `TYPE` constant plus a renderer that implements `supports()` and `render()`. Both are wired as services. Admin UI customisation goes through Twig hooks. Hook names changed wholesale in 2.0 ([UPGRADE-2.0.md](https://github.com/Sylius/CmsPlugin/blob/2.0/UPGRADE-2.0.md)), and constructors changed in 1.2 ([UPGRADE-1.2.md](https://github.com/Sylius/CmsPlugin/blob/1.2/UPGRADE-1.2.md)).

### Pain points from issues

- **The element editor loses data.**
  - Adding or changing an element wipes textarea content: [#82](https://github.com/Sylius/CmsPlugin/issues/82), [#86](https://github.com/Sylius/CmsPlugin/issues/86), [#99](https://github.com/Sylius/CmsPlugin/issues/99), [#104](https://github.com/Sylius/CmsPlugin/issues/104) and [#144](https://github.com/Sylius/CmsPlugin/issues/144), a regression.
  - The browser freezes on a form error: [#93](https://github.com/Sylius/CmsPlugin/issues/93).
  - New custom elements don't show up in the picker: [#140](https://github.com/Sylius/CmsPlugin/issues/140).
- **Elements can't be inserted between others or reordered** ([#100](https://github.com/Sylius/CmsPlugin/issues/100); PRs #159 and #165 followed). Users also want copy/paste of elements ([#190](https://github.com/Sylius/CmsPlugin/issues/190)).
- **Trix is too limited.** A Quill integration was requested ([#125](https://github.com/Sylius/CmsPlugin/issues/125), 4 reactions), and [#73](https://github.com/Sylius/CmsPlugin/issues/73) comments on the same limit.
- **Pages need categories**, or a taxon-to-collection link, for article listings ([#73](https://github.com/Sylius/CmsPlugin/issues/73)).
- **Rendering correctness.**
  - A global `html_entity_decode` undoes Twig escaping ([#202](https://github.com/Sylius/CmsPlugin/issues/202)).
  - Disabled products and taxons are rendered ([#119](https://github.com/Sylius/CmsPlugin/issues/119), [#204](https://github.com/Sylius/CmsPlugin/issues/204)).
  - Preview ignores the chosen locale ([#215](https://github.com/Sylius/CmsPlugin/issues/215)).
  - Slugs handle slashes inconsistently ([#115](https://github.com/Sylius/CmsPlugin/issues/115)).
- **The docs drifted from the code** ([#76](https://github.com/Sylius/CmsPlugin/issues/76)).

## Monsieur Biz suite (Rich Editor + CMS Page + CMS Block + Menu + Homepage)

### Rich Editor ([repo](https://github.com/monsieurbiz/SyliusRichEditorPlugin))

**Content model.** `RichEditorType` turns **any** form field, including product or taxon descriptions through a form extension, into an ordered JSON list of "UI elements". Existing HTML is converted into an HTML element, so no data is lost.

The built-in elements are:

- layout: row, column and two columns;
- HTML, Markdown, text, quote and title;
- image, image collection, video and YouTube;
- button, separator and anchor.

Source: [README](https://github.com/monsieurbiz/SyliusRichEditorPlugin#available-elements).

**Admin UX.** Elements are picked from cards with optional SVG/HTML **wireframes** and have an admin preview. The `WysiwygType` inside text elements is Pell.

**Getting content into shop pages.** Templates use the filter `content|monsieurbiz_richeditor_render_field`, or `monsieurbiz_richeditor_get_elements` and render each element for custom wrappers.

**Extending.** It has the best developer experience of the group:

- A `#[AsUiElement]` attribute or YAML defines an element as a form type plus front and admin templates, and a Maker command scaffolds one.
- **Tags** filter which elements a given field allows, e.g. `'tags' => ['-default', 'product']`.
- Elements can be disabled by config or environment variable. Old content stays editable, but new instances can't be added.
- File fixtures exist for elements.

### CMS Page ([repo](https://github.com/monsieurbiz/SyliusCmsPagePlugin))

**Content model.** `Page` has code, enabled, channels, `publishAt`, `unpublishAt` and timestamps. Each translation has title, content (Rich Editor JSON), slug, and meta title, keywords, description and image (fields from `src/Entity` on branch `1.x`).

**Routing.** Pages resolve through a route condition on slug, current locale, current channel and "now", so publish windows are enforced at routing ([README](https://github.com/monsieurbiz/SyliusCmsPagePlugin#installation)). There is a back-office preview.

**Homepage** ([repo](https://github.com/monsieurbiz/SyliusHomepagePlugin)) works the same way, with one Rich Editor homepage per channel and locale.

### CMS Block ([repo](https://github.com/monsieurbiz/SyliusCmsBlockPlugin))

A reusable block is inserted into Rich Editor content through a `block` UI element and can be disabled centrally. The plugin offers no documented way to place a block in a non-CMS template. [#13](https://github.com/monsieurbiz/SyliusCmsBlockPlugin/issues/13) asks how to render one on a product page, and the workaround is a custom Twig component.

### Menu ([repo](https://github.com/monsieurbiz/SyliusMenuPlugin))

**Model.** `Menu` has code, items and `isSystem`. `MenuItem` is a tree with position, `enabled`, `targetBlank` and the `noopener`, `noreferrer` and `nofollow` flags. Each translation has a **URL** and a label.

**Admin.** A URL picker uses providers for taxons and products, and projects add their own through `UrlProviderInterface`. Front rendering goes through a `monsieur_biz:shared:menu` Twig component or `getMenuItems('code')`.

Per-channel or per-locale menus need the separate Settings plugin ([README](https://github.com/monsieurbiz/SyliusMenuPlugin#customize-front-view)).

### Pain points from issues

- **Media storage is hard-wired to the local filesystem** ([RE#178](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/178)), and all images land in one directory ([RE#111](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/111)).
- **The WYSIWYG is weak.** The toolbar can't be configured per field ([RE#113](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/113), open since 2020), and pasting either keeps junk inline styles or strips all formatting ([RE#115](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/115), open).
- **Translating means rebuilding the structure.** Users asked to copy a locale's element structure to another locale ([RE#93](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/93), open).
- **Layout wrappers came late.** A wrapper/section element with id and classes was requested ([RE#108](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/108)); row and column elements exist now.
- **Admin JS conflicts and broken modals.** See [RE#24](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/24), [RE#94](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/94) (16 comments) and [RE#251](https://github.com/monsieurbiz/SyliusRichEditorPlugin/issues/251).
- **Localized URLs.** Removing the locale prefix is hard ([CP#38](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/38), the most-engaged CMS Page issue), and 404s appear with several locales or a non-default `Accept-Language` ([CP#14](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/14), [CP#78](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/78)).
- **No drafts.** The request ([CP#77](https://github.com/monsieurbiz/SyliusCmsPagePlugin/issues/77)) was answered with publish dates plus preview, not a real draft state.
- **Menus store raw URLs.** Items don't reference taxons or products, so a changed slug breaks the link ([Menu#21](https://github.com/monsieurbiz/SyliusMenuPlugin/issues/21), open). The maintainer prioritises loading a menu in one query. Pages without channels still show up in menus ([Menu#59](https://github.com/monsieurbiz/SyliusMenuPlugin/issues/59)).

## Happy CMS (Adeliom)

Repo: <https://github.com/agence-adeliom/sylius-happy-cms-plugin>. The README claims the richest editorial feature set in the field:

- a visual page builder with a **live iframe preview of the real shop page** and a **desktop, tablet and mobile preview**;
- per-locale editing;
- shared (reusable) blocks;
- custom routable resources (blog, FAQ, brand pages);
- Flysystem-backed media;
- SEO (meta, robots, sitemap) and menus;
- Maker commands, and `#[AsBlock]` / `#[AsSharedBlock]` attributes ([#73](https://github.com/agence-adeliom/sylius-happy-cms-plugin/issues/73));
- an AI assistant.

The 1.13/1.14 line exists but is marked unmaintained.

The issues are mostly the maintainer's own BC-break planning. They show content-model churn: a new persistence model in 2.1, and channel attachment for menus, shared blocks and configs added only in [#153](https://github.com/agence-adeliom/sylius-happy-cms-plugin/issues/153). The preview breaks when the admin runs on its own host ([#89](https://github.com/agence-adeliom/sylius-happy-cms-plugin/issues/89)). Adoption is very low: 3 stars and about 2.3K downloads.

## Sherlockode Advanced Content

Repo: <https://github.com/sherlockode/SyliusAdvancedContentPlugin>. It wraps [AdvancedContentBundle](https://github.com/sherlockode/advanced-content-bundle) and offers:

- row and column layouts with standard elements;
- **auto-saved drafts and rollback to earlier versions**, the only surveyed plugin with versioning;
- "scopes" (channel and locale) passed to the preview `ViewHandlerInterface`;
- custom elements "with a few lines of code".

It needs jQuery UI and Font Awesome in the admin build. Adoption is negligible (0 issues, about 1.9K downloads).

## Netgen Layouts for Sylius

Repo: <https://github.com/netgen-layouts/layouts-sylius>. It works at the layout level, not the content level. The shop layout must wrap `content` in a `layout` block, and full-view templates must extend `nglayouts.layoutTemplate` ([README](https://github.com/netgen-layouts/layouts-sylius#readme)).

Layouts map to **existing** Sylius pages through resolver rules:

- **targets**: Page, Product, Taxon, TaxonProduct and TaxonTree;
- **conditions**: Channel, Locale and ResourceType.

Source: [`lib/Layout/Resolver`](https://github.com/netgen-layouts/layouts-sylius/tree/master/lib/Layout/Resolver). This is the closest existing analogue to v2's "slots in existing shop pages with targeting". Its cost is a separate, heavyweight layouts system and template-inheritance changes.

## Setono v1 open issues (lessons only)

The open requests on <https://github.com/Setono/SyliusCMSPlugin/issues> overlap the gaps above:

- developer-defined content types ([#148](https://github.com/Setono/SyliusCMSPlugin/issues/148));
- injection points ([#47](https://github.com/Setono/SyliusCMSPlugin/issues/47));
- eligibility-based show and hide ([#17](https://github.com/Setono/SyliusCMSPlugin/issues/17));
- block versioning ([#63](https://github.com/Setono/SyliusCMSPlugin/issues/63));
- a media library and asset preview ([#28](https://github.com/Setono/SyliusCMSPlugin/issues/28), [#41](https://github.com/Setono/SyliusCMSPlugin/issues/41));
- navigation in the CMS ([#51](https://github.com/Setono/SyliusCMSPlugin/issues/51));
- articles and posts ([#18](https://github.com/Setono/SyliusCMSPlugin/issues/18));
- forms in pages ([#50](https://github.com/Setono/SyliusCMSPlugin/issues/50));
- front-end inline editing ([#137](https://github.com/Setono/SyliusCMSPlugin/issues/137));
- element tags ([#138](https://github.com/Setono/SyliusCMSPlugin/issues/138));
- CSS-grid templates ([#165](https://github.com/Setono/SyliusCMSPlugin/issues/165));
- editor-supplied classes and styles ([#7](https://github.com/Setono/SyliusCMSPlugin/issues/7)).

---

## Cross-cutting comparison

| Concern | BitBag v4 | Sylius CMS | Monsieur Biz | Happy CMS | Netgen Layouts |
|---|---|---|---|---|---|
| Body model | One HTML field per locale | Typed elements, a list per locale | Typed elements as JSON per locale, nestable layout | Flexible blocks as a JSON list | Blocks in layout zones |
| Placement in existing pages | Developer Twig calls | Developer Twig calls/component, product/taxon rules | Only inside Rich Editor fields | Not documented | Rule-mapped layouts |
| Host-page context in content | No | Rule check only; renderers get none | No (field-level) | Not documented | Yes, via resolver targets |
| Targeting | Channel | Channel, product/taxon, `publishAt` | Channel, publish window | Locale; channels added late | Channel, locale, resource |
| Drafts and versions | No | No | No | No | n/a (not checked) |
| Developer extension | Decorate/override entities | Form type + renderer service | `#[AsUiElement]` + templates, tags, Maker | `#[AsBlock]`, Maker | Block definitions |
| Media storage | Gaufrette, S3 issues | Plugin media | Local only (#178) | Flysystem | n/a |
| Environment transfer | CSV import | Fixtures | Fixtures (file fixtures) | n/a | n/a (not checked) |

## Gaps v2 could fill

1. **A maintained CMS for Sylius 1.14.** Sylius CMS is 2.x only. BitBag v4 is deprecated with no release since March 2025. Monsieur Biz, Happy CMS and Sherlockode all put new work into their Sylius 2 lines.
2. **Slots in existing shop pages with host context.** Nobody combines editor-built content with placement into product, taxon or cart pages that can read the host `product`. Sylius's block `context` only gates display, Monsieur Biz Blocks can't be placed outside CMS content (Block#13), and only Netgen does rule-based placement, at heavy cost.
3. **Real targeting.** Channel-specific variants under one slug (BitBag #549), customer group (supported by none) and date windows (only Monsieur Biz pages and Sylius `publishAt`) belong in one model rather than per-resource flags.
4. **A reliable, modern editor.**
   - Data-loss bugs: Sylius #82–#144.
   - Weak WYSIWYGs: CKEditor 4 is end of life, Trix and Pell are too limited (Sylius #125, RE#113, RE#115).
   - Missing basics: insert, reorder and copy/paste elements (Sylius #100, #190), and copying structure across locales (RE#93).
   - Live and responsive preview exists only in Happy CMS.
5. **A publishing workflow.** Drafts, versions and rollback exist only in Sherlockode. Users keep asking for them (Monsieur Biz CP#77, Setono #63).
6. **Declarative extension without entity overrides.** Attribute-defined element types (`#[AsUiElement]`, `#[AsBlock]`), per-field allow-lists via tags, and safe deprecation of element types are proven patterns. Sylius resource model overrides are where BitBag users struggle most.
7. **Safe rendering.** Twig typed into the WYSIWYG (BitBag #325, #460) and blanket entity decoding (Sylius #202) cause bugs. This supports v2's no-admin-Twig decision and argues for escaping-correct element renderers.
8. **A media library on a storage abstraction**, such as Flysystem, with S3 support and organised paths (RE#178, RE#111, BitBag #431).
9. **Menus that link to entities, not raw URLs.** Links should survive slug changes and respect channel and visibility (Menu#21, Menu#59).
10. **Content transfer between environments, plus caching.** Transfer today is CSV import (BitBag) or fixtures. None of the READMEs or docs I read describe HTTP caching, ESI or invalidation on publish. BitBag's sub-request block rendering and per-block extra queries (#223) suggest it was never designed for.
11. **Collections with categorisation.** Users want page categories and listings (Sylius #73, BitBag sections #113). Happy CMS's "custom routable resources" show demand for blog, FAQ and brand-page types.

## Open questions and limits

- The Sylius CMS docs at docs.sylius.com could not be read anonymously, so its model comes from source on branch `1.2`. The 2.0 alpha changes to the model, beyond the form redesign, were not checked.
- Happy CMS claims come from its README only. Its source and live preview were not verified.
- Monsieur Biz issue numbers carry the prefixes RE (Rich Editor), CP (CMS Page) and Menu. Only 3 of 180 BitBag issues are still open. Whether each closed one was fixed was not checked.
