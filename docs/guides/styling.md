# Styling

The shipped blocks render plain, semantic markup with their own `cms-` classes, and no CSS framework's. The plugin's
stylesheet handles only layout and mechanics, such as grids, columns, carousels, aspect ratios and spacing, and takes
its fonts and colours from the shop. A theme makes the blocks look like the shop by styling the classes, setting the
custom properties and adding block styles, without overriding a template.

## The stylesheet and the cascade layer

The plugin loads `/bundles/setonosyliuscmsplugin/shop/cms.css` on every shop page, through Sylius's
`sylius.shop.layout.stylesheets` template event, before Sylius's own styles. Everything in it sits in the cascade layer
`cms`, so any of the theme's CSS outside a layer wins over it, whatever its specificity:

```css
/* The theme's CSS, outside any layer */
.cms-heading {
    font-family: var(--brand-display-font);
    letter-spacing: 0.02em;
}
```

A theme that uses cascade layers of its own, such as Tailwind 4's, keeps them above `cms`, because the plugin's file
declares the layer first. The stylesheet never styles `html` or `body`.

The carousel script, `/bundles/setonosyliuscmsplugin/shop/cms.js`, loads at the end of the page. A project that bundles
both files into its own build sets `setono_sylius_cms.assets: false`, and the plugin loads neither.

## Class names

Every shipped block's root element has the class `cms-<block type code>`, with underscores turned into hyphens, such as
`cms-rich-text`. Its parts are `cms-<block>__<part>`, and its modifiers `cms-<block>--<modifier>`. These are shared:

| Class | On |
| --- | --- |
| `cms-tree` | The element a layout wraps around the top-level blocks. They're contained to `--cms-content-width`, centred and spaced by `--cms-block-spacing`. |
| `cms-full-width` | A Group or Banner whose Full width is on. Directly inside `cms-tree`, it reaches the edges of the viewport. |
| `cms-slot` and `cms-slot--<identifier>` | The wrapper a slot with something to show prints, such as `cms-slot--product-after-price`. |
| `cms-image-placeholder` | An empty image in the builder's preview. |

Each block type's parts are listed under [Block types](../spec/blocks.md#block-types). The class names are a contract:
renaming one is a breaking change, so a theme can rely on them.

On browsers with classic scrollbars, a full-width block can be a scrollbar's width wider than the page. A theme that then
shows a horizontal scrollbar adds `overflow-x: clip` to `body`.

## Custom properties

The stylesheet reads these, each with the default shown. Set them on `:root`, or on any ancestor of the blocks they
should change:

```css
:root {
    --cms-content-width: 72rem;
    --cms-gap: 1.5rem;
    --cms-button-primary-background: var(--brand-primary);
    --cms-radius: 0;
}

.cms-slot--shop-footer {
    --cms-block-spacing: 1rem;
}
```

| Property | Default | Used for |
| --- | --- | --- |
| `--cms-content-width` | `100%` | The width top-level blocks in `cms-tree` are contained to. The tree already sits inside the shop's `.ui.container`, so set it when your theme's layout has no container. |
| `--cms-block-spacing` | `2rem` | Vertical space between top-level blocks. |
| `--cms-gap` | `1rem` | Gaps in grids, columns and carousels. |
| `--cms-column-min-width` | `240px` | The width below which Columns wrap onto new lines. |
| `--cms-grid-item-min-width` | `220px` | The narrowest card in a grid of products, taxons or entries. |
| `--cms-carousel-per-view` | `1` | Slides shown at once. Set on each `.cms-carousel` from its `data-cms-per-view`, so override it on the element: `.cms-carousel[data-cms-per-view="4"] { --cms-carousel-per-view: 3; }`. |
| `--cms-spacer-small`, `--cms-spacer-medium`, `--cms-spacer-large` | `1rem`, `2rem`, `4rem` | Spacer heights. |
| `--cms-muted-background` | `#f4f4f4` | The Group's `muted` style. |
| `--cms-dark-background`, `--cms-dark-color` | `#1b1c1d`, `#ffffff` | The Group's `dark` style. |
| `--cms-banner-min-height` | `360px` | The Banner's height when its image is shorter. |
| `--cms-banner-overlay` | `rgba(0, 0, 0, 0.35)` | The shade behind a Banner's text. |
| `--cms-banner-color` | `#ffffff` | A Banner's text over its image. |
| `--cms-button-primary-background`, `--cms-button-primary-color` | `#1b1c1d`, `#ffffff` | The Button's `primary` style. |
| `--cms-button-secondary-background`, `--cms-button-secondary-color` | `transparent`, `inherit` | The Button's `secondary` style. |
| `--cms-radius` | `0.25rem` | Rounded corners on buttons and cards. |
| `--cms-placeholder-background` | `#e0e0e0` | The empty-image placeholder in the builder. |
| `--cms-article-width` | `48rem` | The contained width of Blog post's Article layout. |

## Block styles

A block style is a named look editors pick for a block, which adds a class to its root element. The Group ships
`muted` and `dark`, and the Button `primary` and `secondary`. A project adds more to any block type, shipped or its own,
in config:

```yaml
# config/packages/setono_sylius_cms.yaml
setono_sylius_cms:
    block_styles:
        banner:
            brand: { label: 'app.cms.block_style.brand', class: 'app-banner--brand' }
        group:
            highlight: { label: 'app.cms.block_style.highlight', class: 'app-group--highlight' }
```

```css
.cms-banner.app-banner--brand {
    --cms-banner-overlay: rgb(0 40 85 / 0.6);
}
```

The first key is the block type's code, then the style's code, which is stored in each block that uses it. A style that's
later removed renders as no style. A block type of your own declares its styles in `#[BlockType(styles: […])]`
([Block types](block-types.md)).

Editors get block styles, not colour pickers or spacing sliders, so the shop's design stays in the theme
([ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md)). When the markup itself has to change, override the
template ([Overriding templates](overriding-templates.md)).
