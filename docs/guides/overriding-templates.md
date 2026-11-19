# Overriding templates

Every template the plugin ships is overridden the Symfony way: a file at the same path under
`templates/bundles/SetonoSyliusCMSPlugin/` wins over the plugin's. In a Sylius theme, the same path under the theme's
`templates/bundles/SetonoSyliusCMSPlugin/` overrides it for the channels that use the theme. There's no chain of
template suggestions, such as one template per entry or per slot.

Before you override a template, see whether [styling](styling.md) does it: restyling the classes, setting the custom
properties and adding block styles keep working when the plugin's templates change, and an override doesn't.

## What ships

| Template | Renders |
| --- | --- |
| `block/<block type code>.html.twig` | A shipped block, such as `block/heading.html.twig` or `block/rich_text.html.twig`. `block/_carousel.html.twig` and `block/_pagination.html.twig` are the carousel and pagination markup the blocks share. |
| `layout/base.html.twig` | What every layout extends: Sylius's shop layout, with the entry's title and head tags. |
| `layout/<content type code>/<layout code>.html.twig` | A shipped layout, such as `layout/page/standard.html.twig` or `layout/blog_post/article.html.twig`. |
| `card/<content type code>/<display>.html.twig` | A shipped card, such as `card/blog_post/grid.html.twig`. It gets `entry` and `display`. |
| `slot/slot.html.twig` | The wrapper a slot with something to show prints around its snippets. |

They're all in [`src/Resources/views`](../../src/Resources/views). The variables a block template and a layout template
get are in [Block types](block-types.md#the-template) and [Layouts](layouts.md#the-template).

## An example

To give the Heading block an anchor icon, copy the plugin's `block/heading.html.twig` and change it:

```twig
{# templates/bundles/SetonoSyliusCMSPlugin/block/heading.html.twig #}
<{{ block.level }} id="{{ view.key }}" class="cms-heading{{ view.styleClass ? ' ' ~ view.styleClass }}">
    {{- cms_field(block, 'text') -}}
    <a class="app-heading-anchor" href="#{{ view.key }}" aria-hidden="true">#</a>
</{{ block.level }}>
```

Or wrap the original, which `@!SetonoSyliusCMSPlugin` names, so the plugin's changes to it still reach you:

```twig
{# templates/bundles/SetonoSyliusCMSPlugin/block/heading.html.twig #}
<div class="app-heading-wrapper">
    {{- include('@!SetonoSyliusCMSPlugin/block/heading.html.twig') -}}
</div>
```

Clear the cache after adding an override: `bin/console cache:clear`.

## What an override keeps

- **`cms_field()` for every field it prints**, so editors can still type in the builder's preview. `block.text` prints
  the same text in the shop, but the builder can't edit it in place.
- **`view.styleClass` on the root element**, so block styles still apply.
- **The `cms-` class names**, unless the theme styles its own: the plugin's stylesheet and carousel script look for them.
- **`cms_blocks(children)`** in a block that holds others, and **`cms_blocks(blocks)` inside a `cms-tree`** in a layout.

## Changing more than the markup

An override changes how a block looks, not what editors fill in. A shipped block type's version and upgraders belong to
the plugin, so a project never adds fields to it. To change its fields, register a block type of your own with its own
code ([Block types](block-types.md)). It can keep the shipped fields' names and reuse the shipped template, such as
`#[BlockType(code: 'app_heading', …, template: '@SetonoSyliusCMSPlugin/block/heading.html.twig')]`. Then switch the
shipped one off ([Switching off what ships](switching-off.md)).
