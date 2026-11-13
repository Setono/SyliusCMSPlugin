# Blocks

The block types the plugin ships, their markup and styling contract, block styles, and how projects switch off or replace what ships.

- **Terms:** [block, block type, block style, field](../../GLOSSARY.md#content-structure), [snippet](../../GLOSSARY.md#entries), [visibility condition](../../GLOSSARY.md#visibility), [media item](../../GLOSSARY.md#media).
- **Decisions:** [ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md) (shipped blocks are theme-agnostic), [ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md) (the opt-in Embed code block), [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (no repeater field), [ADR 0008](../adr/0008-the-builder-is-a-react-app-with-a-twig-preview-in-a-same-origin-iframe.md) (storefront JS initialises on the bridge event).

## Styling

Shipped blocks render plain semantic markup with their own `cms-` classes, not the markup of a CSS framework ([ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md)).

- **The stylesheet** handles only layout and mechanics: grids, columns, carousels, aspect ratios and spacing. It inherits the shop's fonts and colours.
- **It sits in the cascade layer `cms`** (`@layer cms { … }`), so any theme CSS outside a layer wins without specificity fights.
- **Editors get block styles, not design controls.** There are no colour pickers, spacing sliders or font sizes.
- **The class names and the custom properties below are a contract.** Agencies style against them, so renaming one is a breaking change.

### Class names

Every shipped block's root element has the class `cms-<block type code>`, with underscores turned into hyphens, such as `cms-rich-text`. Inner parts use `cms-<block>__<part>` and modifiers use `cms-<block>--<modifier>`. Each block type's parts are listed under [Block types](#block-types).

These classes are shared:

| Class | On | Meaning |
| --- | --- | --- |
| `cms-tree` | the element a layout wraps around the top-level blocks | Top-level blocks inside it are contained to `--cms-content-width` and centred, except those marked `cms-full-width`. They're spaced by `--cms-block-spacing`. |
| `cms-full-width` | a Group or Banner root whose `fullWidth` is on | Directly inside `cms-tree`, it breaks out of the shop's container to the edges of the viewport ([ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md)), with `width: 100vw; margin-inline: calc(50% - 50vw)`. Anywhere else, such as in a slot or a column, it fills its surroundings. |
| `cms-slot` | the wrapper a non-empty slot prints around its snippets' top-level blocks | See [Slots](slots.md#rendering-a-slot). |
| `cms-image-placeholder` | an empty image in the builder's preview and the preset browser | A neutral grey box at the image's aspect ratio. |

A chosen block style adds its class to the block's root element, next to `cms-<block>`.

**Full width and scrollbars.** On browsers with classic scrollbars, `100vw` includes the vertical scrollbar, so a full-width block can be a scrollbar's width wider than the page. The stylesheet never styles `html` or `body`, so a theme that then shows a horizontal scrollbar adds `overflow-x: clip` to `body`.

### Custom properties

The stylesheet reads these, each with the default shown. Themes set them on `:root` or on any ancestor, except `--cms-carousel-per-view`, which the stylesheet sets on each carousel ([Carousel](#carousel)).

| Property | Default | Used for |
| --- | --- | --- |
| `--cms-content-width` | `100%` | The width top-level blocks in `cms-tree` are contained to. The tree already sits inside the shop layout's `.ui.container`, so by default blocks are exactly as wide as the shop's own content. A theme whose layout has no container sets a width here. |
| `--cms-block-spacing` | `2rem` | Vertical space between top-level blocks. |
| `--cms-gap` | `1rem` | Gaps in grids, columns and carousels. |
| `--cms-column-min-width` | `240px` | The width below which Columns wrap onto new lines. |
| `--cms-grid-item-min-width` | `220px` | The narrowest card or tile in a grid of products, taxons or entries. |
| `--cms-carousel-per-view` | `1` | Slides shown at once in a carousel. The stylesheet sets it on each `.cms-carousel` from its `data-cms-per-view`. A theme overrides it on the element itself, such as `.cms-carousel[data-cms-per-view="4"] { --cms-carousel-per-view: 3; }`. |
| `--cms-spacer-small`, `--cms-spacer-medium`, `--cms-spacer-large` | `1rem`, `2rem`, `4rem` | Spacer heights. |
| `--cms-muted-background` | `#f4f4f4` | The Group's `muted` style. |
| `--cms-dark-background`, `--cms-dark-color` | `#1b1c1d`, `#ffffff` | The Group's `dark` style. |
| `--cms-banner-min-height` | `360px` | The Banner's height when its image is shorter. |
| `--cms-banner-overlay` | `rgba(0, 0, 0, 0.35)` | The shade behind a Banner's text. |
| `--cms-button-primary-background`, `--cms-button-primary-color` | `#1b1c1d`, `#ffffff` | The Button's `primary` style. |
| `--cms-button-secondary-background`, `--cms-button-secondary-color` | `transparent`, `inherit` | The Button's `secondary` style. |
| `--cms-radius` | `0.25rem` | Rounded corners on buttons and tiles. |
| `--cms-placeholder-background` | `#e0e0e0` | The empty-image placeholder. |

### Loading the stylesheet and script

The plugin ships the stylesheet and the carousel script prebuilt, installed by `assets:install`:

- `/bundles/setonosyliuscmsplugin/shop/cms.css`, through the `setono_sylius_cms_stylesheet` block on the `sylius.shop.layout.stylesheets` template event, at priority 20. That's above Sylius's own `styles` block (10), so the file loads before the theme's CSS and declares the `cms` layer first. A theme that uses cascade layers of its own, such as Tailwind 4's, then keeps them above `cms`.
- `/bundles/setonosyliuscmsplugin/shop/cms.js`, through the `setono_sylius_cms_javascript` block on the `sylius.shop.layout.javascripts` template event

`setono_sylius_cms.assets: false` removes both blocks, for agencies that bundle the files into their own build ([Extending](extending.md#configuration)).

## Block styles

A block type offers named looks in code (`#[BlockType(styles: […])]`), and a project adds more to any block type, shipped or not, in config ([Extending](extending.md#configuration)):

```yaml
setono_sylius_cms:
    block_styles:
        banner:
            brand: { label: 'app.cms.block_style.brand', class: 'app-banner--brand' }
```

A block stores the chosen style's code in its `style`, or `null` for none. A style code that's no longer declared renders as no style.

Shipped styles:

| Block type | Code | Class |
| --- | --- | --- |
| Group | `muted` | `cms-group--muted` |
| Group | `dark` | `cms-group--dark` |
| Button | `primary` | `cms-button--primary` |
| Button | `secondary` | `cms-button--secondary` |

A Group with no style has no background.

## Block types

Each shipped block type's template is `@SetonoSyliusCMSPlugin/block/<code>.html.twig`. Every template prints its fields through `cms_field()`, so the builder can type them in place ([Twig](extending.md#twig)).

"All" in the children column means `Allow('*')`: any block type that doesn't limit its parents. None of the shipped block types needs host-page context itself. Sources and snippets inside them can ([Sources](sources.md), [Slots](slots.md#snippets-and-their-context)).

| Group | Code | Children | Parents |
| --- | --- | --- | --- |
| Structure | `group` | all | |
| Structure | `columns` | `column`, 2 to 4 | |
| Structure | `column` | all | `columns` |
| Structure | `spacer` | none | |
| Content | `heading` | none | |
| Content | `rich_text` | none | |
| Content | `image` | none | |
| Content | `video` | none | |
| Content | `button` | none | |
| Content | `banner` | `button` | |
| Content | `accordion` | `accordion_item`, at least 1 | |
| Content | `accordion_item` | none | `accordion` |
| Content | `link_list` | `link` | |
| Content | `link` | none | `link_list` |
| Content | `downloads` | none | |
| Commerce | `products` | none | |
| Commerce | `taxons` | none | |
| Collections | `entries` | none | |
| Carousels | `carousel` | all | |
| Conditional | `first_match` | all | |
| Reuse | `snippet` | none | |
| Opt-in | `embed_code` | none | |

A container that allows only one block type, such as Columns, Accordion or Link list, adds that block straight away when it's inserted ([Builder](builder.md)).

### Group

A wrapper for other blocks, with a background from its block style.

| Field | Field type | Translatable |
| --- | --- | --- |
| `fullWidth` | Toggle, off by default | no |

Markup: `cms-group`, with `cms-group__inner` contained to `--cms-content-width` inside it, so a full-width background keeps its content contained. The wrapper is called Group because the glossary avoids "section" for blocks.

### Columns and Column

Columns lays its Column children side by side at equal widths, and wraps them below `--cms-column-min-width`. Neither has fields.

Markup: `cms-columns` holding `cms-column`s.

### Spacer

| Field | Field type | Translatable |
| --- | --- | --- |
| `size` | Choice: `small`, `medium` (default), `large` | no |

Markup: `cms-spacer cms-spacer--<size>`.

### Heading

| Field | Field type | Translatable |
| --- | --- | --- |
| `text` | Text, required, bindable | yes |
| `level` | Choice: `h1`, `h2` (default), `h3`, `h4` | no |

Markup: an `<h1>` to `<h4>` with the class `cms-heading`.

### Rich text

| Field | Field type | Translatable |
| --- | --- | --- |
| `content` | Rich text | yes |

Markup: `cms-rich-text` around the rendered HTML.

### Image

| Field | Field type | Translatable |
| --- | --- | --- |
| `image` | Media (`image`), required | no |
| `alt` | Text: overrides the media item's alt text for this use | yes |
| `caption` | Text | yes |
| `link` | Link | label and URL |

Markup: `<figure class="cms-image">` with the image from `cms_image()` and `<figcaption class="cms-image__caption">`. Captions are block fields, because they depend on where the image is used ([Media](media.md)).

### Video

| Field | Field type | Translatable |
| --- | --- | --- |
| `video` | Media (`video`) | no |
| `embedUrl` | Text: a YouTube or Vimeo URL | no |
| `poster` | Media (`image`) | no |

Exactly one of `video` and `embedUrl` is set, which publish validates. YouTube and Vimeo videos are a URL, not media items. A video media item's own poster is used when `poster` is empty.

Markup: `cms-video`, with `cms-video__frame` holding the `<video>` or the embed's `<iframe>` at 16:9.

### Button

| Field | Field type | Translatable |
| --- | --- | --- |
| `link` | Link, required | label and URL |

Markup: `<a class="cms-button">`, plus the style's class. The label is the link's label, or its target's name. A link that doesn't resolve renders no button. A [bound link](content-model.md#links), such as one to the host page's product, has no target name, so publish requires a label for it.

### Banner

An image with an optional mobile image, a heading, text and buttons. Its buttons are Button children.

| Field | Field type | Translatable |
| --- | --- | --- |
| `image` | Media (`image`) | no |
| `mobileImage` | Media (`image`): shown below the shop's mobile breakpoint | no |
| `alt` | Text: overrides the image's alt text | yes |
| `heading` | Text, bindable | yes |
| `text` | Rich text | yes |
| `fullWidth` | Toggle, off by default | no |

Markup: `cms-banner`, with `cms-banner__media` (a `<picture>` with the mobile image as a narrow source), `cms-banner__content`, `cms-banner__heading`, `cms-banner__text` and `cms-banner__buttons`.

### Accordion and Accordion item

Accordion has no fields. Accordion item:

| Field | Field type | Translatable |
| --- | --- | --- |
| `title` | Text, required | yes |
| `content` | Rich text | yes |

Markup: `cms-accordion` holding `<details class="cms-accordion-item">`, each with `<summary class="cms-accordion-item__title">` and `cms-accordion-item__content`. It needs no JS.

### Link list and Link

Link list stands in for simple navigation, such as footer links in a snippet, until menus arrive ([ADR 0003](../adr/0003-menus-are-entries-with-a-block-tree.md)). Each link is a Link child block, because a link has fields of its own and there's no repeater ([ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md)).

Link list:

| Field | Field type | Translatable |
| --- | --- | --- |
| `heading` | Text | yes |

Link:

| Field | Field type | Translatable |
| --- | --- | --- |
| `link` | Link, required | label and URL |

Markup: `<nav class="cms-link-list">` with `cms-link-list__heading` and `<ul class="cms-link-list__links">`, holding `<li class="cms-link">`s. A link that doesn't resolve renders no `<li>`. In the builder's tree, a Link's row has an editable label box ([Builder](builder.md)).

### Downloads

| Field | Field type | Translatable |
| --- | --- | --- |
| `heading` | Text | yes |
| `documents` | Media list (`document`) | no |

Markup: `cms-downloads`, with `cms-downloads__heading` and a list of `cms-downloads__item` links to each document's permanent URL ([Media](media.md#permanent-urls)).

### Products

A list of products filled by hand or by a source ([Sources](sources.md)), shown as a grid or a carousel.

| Field | Field type | Translatable |
| --- | --- | --- |
| `products` | Products, `limit: 8` | no |
| `display` | Choice: `grid` (default), `carousel` | no |

Each product renders through the shop's own product card, `@SyliusShop/Product/_box.html.twig`, so CMS product lists match the shop's other lists on every theme. The block adds only the grid or the carousel around the cards.

Markup: `cms-products cms-products--<display>`, holding a `cms-products__item` per product. The carousel display wraps them in the [carousel markup](#carousel). The Products block shows the first N items and doesn't paginate in v2.0.

### Taxons

| Field | Field type | Translatable |
| --- | --- | --- |
| `taxons` | Taxons, `limit: 8` | no |

Markup: `cms-taxons`, a grid of `cms-taxons__item` tiles, each linking to the taxon's page with its first image, through Sylius's own images, and its name in `cms-taxons__name`. Taxon images aren't media items.

### Entries

Lists entries of a collection through their content type's cards. See [Collections](collections.md#the-entries-block).

### Carousel

A carousel whose slides are any child blocks. It covers the three carousel forms with the Products block:

- **Banner slides:** a Carousel of Banners, shipped as the `banner_carousel` preset ([Layouts and presets](layouts-and-presets.md#shipped-code-presets)). Each slide has its own visibility conditions.
- **A product carousel:** the Products block with `display: carousel`.
- **A carousel of any blocks:** the Carousel block itself.

| Field | Field type | Translatable |
| --- | --- | --- |
| `perView` | Choice: `1` (default), `2`, `3`, `4`: slides shown at once on wide screens | no |
| `autoplay` | Toggle, off by default | no |
| `interval` | Number, integer, seconds, 2 to 30, default 5 | no |

Only visible children become slides, so the slide count and the dots are always right ([Rendering](rendering.md)).

Markup:

```html
<div class="cms-carousel" data-cms-per-view="1" data-cms-autoplay="5000">
    <div class="cms-carousel__track">
        <div class="cms-carousel__slide">…</div>
    </div>
</div>
```

`data-cms-per-view` holds the editor's `perView`, and the Products and Entries carousels print `4` and `3`. `data-cms-autoplay` holds the interval in milliseconds and is left out when autoplay is off.

- **Slides per view.** Inside `@layer cms`, the stylesheet sets `--cms-carousel-per-view` on each carousel from `data-cms-per-view`, and the slide width follows from it. No inline style is printed, so theme CSS always wins.
- **Narrow screens.** Below `48rem` the stylesheet shows at most 2 slides, and below `30rem` 1, whatever `data-cms-per-view` says. A theme overrides these the same way.

- **It works without JS.** The track scrolls with CSS scroll-snap.
- **The script adds** `cms-carousel__prev` and `cms-carousel__next` buttons, a `cms-carousel__dots` list of `cms-carousel__dot` buttons, and autoplay, which pauses while the pointer or focus is inside. It has no dependencies.
- **It initialises** every `.cms-carousel` on page load, and every `.cms-carousel` inside the target of a `cms:render` event, which the builder's bridge dispatches from each block it patches in ([Builder](builder.md)). Initialising twice does nothing.

### First match

Shows only its first child whose visibility conditions match, in tree order, like a capped slot with a cap of 1 inside a block tree ([Targeting](targeting.md)). It has no fields and no markup of its own.

Its main use is a fallback, such as a campaign banner while its date window is open and a default banner otherwise, which "is not one of" can't express for a date window. The `campaign_with_fallback` preset sets this up.

### Snippet

A reference to a snippet that stays in sync wherever it's used.

| Field | Field type | Translatable |
| --- | --- | --- |
| `snippet` | Snippet, required | no |

- It renders the snippet's block tree with no markup of its own.
- It's offered only where the host-page context the snippet declares is available ([Slots](slots.md#snippets-and-their-context)).
- A snippet that isn't visible renders nothing ([Content model](content-model.md#when-an-entry-shows)).
- "Detach to edit here" replaces the block with a copy of the snippet's blocks ([Builder](builder.md)).

### Embed code

Raw HTML, scripts included, printed unescaped in the shop ([ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md)).

| Field | Field type | Translatable |
| --- | --- | --- |
| `code` | Code | no |

- **It's off until a project sets `setono_sylius_cms.embed_code: true`.** While it's off, editors can't add it, and publish and import refuse an `embed_code` block whose key isn't already in the entry's published tree. So neither a hand-made draft save nor an import can bring one in ([Publishing](publishing.md#validation), [Transfer](transfer.md#code-the-target-must-have)). Blocks published while it was on keep rendering ([ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md)).
- **The builder's preview and the preset browser** show a placeholder, `cms-embed-code cms-embed-code--placeholder`, in its place, so third-party scripts never run inside the builder.
- In the shop it renders `<div class="cms-embed-code">` around the code.

## Empty images

An image field with no value, such as one left empty by an exported code preset ([Layouts and presets](layouts-and-presets.md#code-presets)):

- renders a neutral grey box, `cms-image-placeholder`, at the image's aspect ratio in the builder's preview and the preset browser, taking the `aspect_ratio` passed to `cms_image()`, or 16:9
- renders nothing in the shop

## Not in v2.0

- Forms, such as newsletter or contact forms.
- Tabs, countdowns and testimonials.
- An icon field type. SVG images are used for icons.
- Layouts beyond one per shipped content type with a URL ([Layouts and presets](layouts-and-presets.md#shipped-layouts)).

## Switching off and replacing what ships

- **Disabling.** `setono_sylius_cms.disabled.block_types` lists shipped block type codes to switch off. Editors no longer see a disabled block type in the picker. Blocks of that type already in trees keep rendering. Presets that use it are marked in the Presets tab ([Layouts and presets](layouts-and-presets.md#presets-with-invalid-blocks)).
- **Overriding templates.** A project overrides `@SetonoSyliusCMSPlugin/block/<code>.html.twig` through a Symfony bundle override (`templates/bundles/SetonoSyliusCMSPlugin/block/<code>.html.twig`) or a Sylius theme ([Twig](extending.md#twig)). An overriding template keeps calling `cms_field()` so typing in place still works.
- **Copy, don't extend.** To change a shipped block type's fields, a project registers its own block type with its own code, which may reuse the shipped class and template in PHP and Twig. Projects never add fields to a shipped block type, because its version and upgraders belong to the plugin.
- **New looks** need no new block type: projects restyle the classes, set the custom properties, add block styles, or override templates.
