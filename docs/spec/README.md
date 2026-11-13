# Setono Sylius CMS v2: specification

v2 of `setono/sylius-cms-plugin` is a CMS for Sylius 1.14, rebuilt from scratch on the `2.x` branch. Developers at agencies define what content can look like in code. Merchants compose content in a full-screen [builder](../../GLOSSARY.md#editing), publish it, and show it on its own URLs and in [slots](../../GLOSSARY.md#shop-pages) in the existing shop pages.

This specification is the source of truth for building v2. It was assembled from the resolutions on [Map: Sylius CMS Plugin v2 spec](https://github.com/Setono/SyliusCMSPlugin/issues/186), which are now history.

- The words it uses are defined in [`GLOSSARY.md`](../../GLOSSARY.md). Read it first. Every chapter uses the glossary's terms and avoids the words it lists under _Avoid_.
- The reasons behind the hard-to-reverse calls are in [`docs/adr/`](../adr/). The spec links to them and doesn't repeat them.

## How to read it

**Public contracts are spelled out exactly.** These are the PHP attributes and their options, the PHP interfaces, the event classes, the bundle config keys, the console commands, the routes, the Twig functions, the CSS class names, and the JSON formats of the stored block tree, drafts, presets and exports. After 2.0 they fall under the backward-compatibility promise, and a change to one of them is a breaking change.

**Internals are left to the builder of the plugin.** That covers services, class layout and database columns, unless an ADR fixes them. Where the spec names a class that isn't listed in [Extending](extending.md), the name is a suggestion.

**Names.** The PHP namespace is `Setono\SyliusCMSPlugin`, and the bundle is `SetonoSyliusCMSPlugin`. The config root is `setono_sylius_cms`, console commands are `setono:cms:*`, Twig functions are `cms_*`, CSS classes are `cms-*`, and Sylius resource aliases are `setono_sylius_cms.*`.

**Codes.** Every code the spec defines (content types, block types, sources, condition kinds and so on) matches `^[a-z][a-z0-9_]*$`. A code is stored in content, so it never changes once it ships.

## Platform

- **Sylius 1.14 only.** This was reaffirmed on 2026-11-13, knowing that 1.14's security support ends in December 2026. A Sylius 2.x port comes later as a separate effort. Keeping that port cheap is nice to have, not a hard constraint.
- **PHP ≥ 8.2**, **Symfony 6.4 only**, and **Twig 3**.
- **Databases:** the ones Sylius supports, MySQL 8.0+ and PostgreSQL. Every query the spec relies on, such as the category reference table, works on both.
- **What v2 builds on**, all of it already in a Sylius 1.14 install unless marked:
  - Doctrine ORM and Sylius's ResourceBundle and GridBundle
  - Symfony Messenger, on Sylius's `main` transport, for scheduled publishes, admin imports and deleting replaced media files
  - LiipImagine, behind the plugin's own image provider ([ADR 0014](../adr/0014-images-are-processed-by-liip-behind-an-image-provider.md))
  - Flysystem, through the plugin's own storage
  - Pagerfanta's `AdapterInterface` (`^3` on 1.14) as part of the source contract ([ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md))
  - `ueberdosis/tiptap-php` for rich text (new dependency)
- **No Symfony UX, no Encore entry and no Node in host projects.** The builder ships prebuilt ([ADR 0008](../adr/0008-the-builder-is-a-react-app-with-a-twig-preview-in-a-same-origin-iframe.md)).

## Scope

v2.0 covers:

- [Entries](../../GLOSSARY.md#entries) of developer-defined content types, with a block tree, fields and translations. [Page](../../GLOSSARY.md#entries) and [Snippet](../../GLOSSARY.md#entries) are the built-in content types, and a Blog and an FAQ ship as [collections](../../GLOSSARY.md#entries).
- Snippets in slots of the existing shop pages, using the [host-page context](../../GLOSSARY.md#shop-pages).
- A library of about 20 shipped block types, including carousels in all three forms (banner slides, product carousels and carousels of any blocks).
- The builder: a React app with a live preview of the real shop page.
- Publishing: one shared draft, scheduled publishes, revisions and share links.
- Targeting: visibility conditions on blocks, an audience on entries, and page scopes on placements.
- Layouts and presets.
- The media library, with focal points and image variants.
- Entry URLs, the homepage, and SEO tags.
- Transfer of content between environments.
- Extension points for developers.

## Out of scope

- **Migrating v1 data to v2.** v2 is a clean break. Migration tooling would be a separate, later effort.
- **Sylius 2.x support.** A separate port after v2 ships on Sylius 1.14.
- **Admin-editable Twig.** Ruled out for security, UX and maintainability. The `BlockRendererInterface` seam lets a sandboxed code block be added later ([Extending](extending.md#block-renderers)). Raw HTML is a separate, opt-in block ([ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md)).
- **AI assist.** No AI generation, rewriting or translation. The per-field translation state is the seam a future translator fills ([Extending](extending.md#translation-state)).
- **Translation export and import**, such as XLIFF or CSV for translation agencies.
- **Editor roles and permissions.** Sylius 1.x OSS has no RBAC to build on, so every admin user can do everything.
- **Storefront search of CMS content.**
- **A headless API.** Twig comes first. The model is API-ready: the [API-readiness check](https://github.com/Setono/SyliusCMSPlugin/issues/209) found that API Platform can expose it later with adapters only.
- **Menus.** Theme templates keep their own navigation. Menus come later as one more built-in content type, in the shape [ADR 0003](../adr/0003-menus-are-entries-with-a-block-tree.md) records. The Link list block stands in for simple footer links.
- **Releases**, meaning bundles of entries published together.
- **Revision diffs.**
- **A page tree and breadcrumbs.** Entries are flat.
- **An XML sitemap, structured data (JSON-LD) and Open Graph tags.** Open Graph is left to SEO plugins such as `setono/sylius-seo-plugin`.
- **CMS-built error pages and manual redirects.** Error pages stay Sylius's own, and manual redirects stay with `setono/sylius-redirect-plugin`. The CMS only redirects an entry's old slugs.
- **Output caching.** v2.0 caches nothing it renders and sends no HTTP cache metadata for reverse proxies ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)).
- **Forms, tabs, countdowns, testimonials and an icon field type** in the block library.
- **Store locations** as a shipped collection, tags, an author on blog posts, date archives and RSS.
- **Private media files**, media tags, per-use crops and video transcoding.

## Testing

Every build ticket ships with tests at each level that applies, and a slice isn't done without them:

- **Unit tests.** PHPUnit for PHP classes, and Vitest for the builder's TypeScript, such as tree operations, undo and forms rendered from the field schema.
- **Functional tests.** PHPUnit with Symfony's kernel and web test cases, against the test app and a real database. They cover controllers, the builder's JSON endpoints, routing, rendering, publishing, transfer and console commands.
- **UI tests.** [`playwright-php/playwright-symfony`](https://github.com/playwright-php/playwright-symfony) drives a real browser through the builder and the storefront.

These three levels replace Behat, so the skeleton's `behat/behat` dev dependency is dropped. Each build ticket's acceptance criteria name the tests it adds.

## How the build starts

1. The first build ticket applies [`Setono/SyliusPluginSkeleton@1.14.x`](https://github.com/Setono/SyliusPluginSkeleton/tree/1.14.x) as the template on `2.x`. It keeps `GLOSSARY.md` and `docs/`, raises `php` to `>=8.2`, and drops Behat.
2. A walking skeleton follows: a page with one block type, edited in a bare builder, published and rendered at its URL.
3. Then vertical slices, each adding one capability end to end and sized to one agent session. Each links to the spec sections it implements. Build tickets are children of a "v2.0 build" parent issue, carry the triage labels and use GitHub's native blocking.

## Chapters

| Chapter | Covers |
| --- | --- |
| [Content model](content-model.md) | entries, content types, the block tree and its stored JSON format, fields, references |
| [Blocks](blocks.md) | the shipped block types, block styles, the stylesheet and carousel script |
| [Sources](sources.md) | sources, the visible queries, paging, Bestsellers |
| [Localization](localization.md) | translations, the fallback chain, availability, completeness and outdated flags |
| [Targeting](targeting.md) | visibility conditions, the audience, capped slots, Shown to and Date |
| [Publishing](publishing.md) | drafts, autosave, publishing, scheduling, revisions, share links |
| [Slots](slots.md) | slots, the stock slots, context keys, placements, page scopes, bindings |
| [Layouts and presets](layouts-and-presets.md) | layouts, presets, starting points, the preset file format |
| [Media](media.md) | the media library, uploads, the usage index, image processing |
| [URLs and SEO](urls-and-seo.md) | entry routing, slugs, old slugs, the homepage, SEO tags |
| [Collections](collections.md) | the Blog and the FAQ, categories, the Entries block, cards, listings |
| [Rendering](rendering.md) | the storefront rendering pipeline, failures, the profiler panel |
| [Builder](builder.md) | the builder app, preview mode, the bridge, the JSON endpoints |
| [Admin](admin.md) | the Content hub, entry lists and overview pages, the library screens |
| [Transfer](transfer.md) | export, import and the export format |
| [Extending](extending.md) | attributes, field types, interfaces, events, config, commands, Twig |
