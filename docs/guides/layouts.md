# Adding a layout

A [layout](../../GLOSSARY.md#entries) is the frame an entry with its own URL renders in: the shop's header and footer,
and around the entry's blocks whatever the layout puts there. Editors pick one per entry in the builder, and switch
between them without losing content. A layout is a PHP class with `#[Layout]`, whose public typed properties are its
fields, and a Twig template.

## The class

```php
// src/CMS/Layout/Landing.php
namespace App\CMS\Layout;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

#[Layout(
    code: 'landing',
    contentType: Page::class,
    label: 'app.cms.layout.landing',
    template: 'cms/layout/landing.html.twig',
)]
final class Landing
{
    #[Text(label: 'app.cms.field.tagline')]
    public ?string $tagline = null;

    #[Toggle(label: 'app.cms.field.transparent_header')]
    public bool $transparentHeader = false;
}
```

- **`code`** is unique within its content type and stored in each entry's block tree, so it never changes once content
  uses it.
- **`contentType`** is the model of a content type with its own URL, such as `Page::class`, `BlogPost::class` or one of
  your own ([Content types](content-types.md)). A content type without a URL, such as Snippet, has no layouts.
- **Its fields** work as a block type's do ([Block types](block-types.md#the-class)). Their values are stored in the
  block tree, beside the blocks, so editors set them per entry.
- **`default: true`** makes it the content type's default, which new entries get. Each content type with its own URL has
  exactly one. `setono_sylius_cms.content_types.<code>.default_layout` names another one in config, and wins.
- **`shows`** lists the entry fields its template prints, such as `['title']`, so the builder marks the others "Not
  shown in this layout". **`slots`** lists the [slots](../../GLOSSARY.md#shop-pages) its template renders with
  `cms_slot()`. **`thumbnail`** is a public path to the card editors pick it by, and **`version`** works as a block
  type's.

Autoconfiguration registers it, as it does a block type. Add the labels to `translations/messages.en.yaml`.

## The template

A layout template extends `@SetonoSyliusCMSPlugin/layout/base.html.twig` and fills its `cms_content` block:

```twig
{# templates/cms/layout/landing.html.twig #}
{% extends '@SetonoSyliusCMSPlugin/layout/base.html.twig' %}

{% block cms_content %}
    <header class="app-landing{{ layout.transparentHeader ? ' app-landing--transparent' : '' }}">
        <p class="app-landing__tagline">{{ cms_field(layout, 'tagline') }}</p>
    </header>

    <div class="cms-tree">
        {{ cms_blocks(blocks) }}
    </div>
{% endblock %}
```

| Variable | Value |
| --- | --- |
| `entry` | The entry being shown. |
| `layout` | The layout object, its properties holding the layout's fields. |
| `blocks` | The top-level blocks that show, as `BlockView`s. `cms_blocks(blocks)` renders them. |
| `context` | The host-page context: `{entry: entry}`. |

- **`base.html.twig` extends Sylius's shop layout**, `@SyliusShop/layout.html.twig`. It fills Sylius's `title` block
  with `cms_meta_title(entry)` and its `metatags` block with `cms_head_tags(entry)`, the meta description, robots,
  canonical and hreflang tags. A layout that overrides either block calls the same function.
- **Wrap the blocks in `cms-tree`**, so they're contained and spaced like every other layout's ([Styling](styling.md)).
- **Print entry fields with `cms_field(entry, 'title')`** and layout fields with `cms_field(layout, 'tagline')`, so
  editors can type in them in the preview.
- **A layout's own slots** render with `cms_slot()`, passing the entry when the slot provides it:

  ```twig
  {{ cms_slot('app.landing.after_blocks', { entry: entry }) }}
  ```

  Declare the slot in config, and list it in the layout's `slots`, so a snippet placed in it previews on an entry in this
  layout ([Slots](../spec/slots.md#rendering-a-slot)):

  ```yaml
  setono_sylius_cms:
      slots:
          app.landing.after_blocks:
              label: 'app.cms.slot.landing_after_blocks'
              provides: ['entry']
  ```

## More

- The shipped layouts, Page's Standard, Blog post's Article and Blog category's Standard, are in
  `src/Resources/views/layout/`. Override their templates as in [Overriding templates](overriding-templates.md).
- `setono_sylius_cms.disabled.layouts` hides a shipped layout from editors ([Switching off what ships](switching-off.md)).
- The test application's layouts: `tests/Application/Layout/Landing.php` and, for a content type of its own,
  `tests/Application/Layout/Store.php`.
