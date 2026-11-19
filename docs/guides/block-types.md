# Adding a block type

A [block type](../../GLOSSARY.md#content-structure) is a PHP class with `#[BlockType]`, whose public typed properties are
its fields, and a Twig template. Editors then find it in the builder's block picker.

## The class

```php
// src/CMS/Block/Hero.php
namespace App\CMS\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Block\Button;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[BlockType(
    code: 'app_hero',
    label: 'app.cms.block_type.hero',
    template: 'cms/block/hero.html.twig',
    children: [new Allow(Button::class, max: 2)],
    styles: [new Style('dark', 'app.cms.block_style.dark', 'app-hero--dark')],
)]
final class Hero
{
    #[Text(label: 'app.cms.field.heading', required: true)]
    #[Assert\Length(max: 80)]
    public ?string $heading = null;

    #[Media(label: 'app.cms.field.image', kinds: ['image'])]
    public ?MediaItemInterface $image = null;
}
```

- **The code** is stored in every block of the type, so it never changes once content uses it. It matches
  `^[a-z][a-z0-9_]*$`, and two block types with one code fail the container build. Prefix yours, such as `app_hero`.
- **Labels** are translation keys in the `messages` domain, translated in the admin user's locale.
- **Each field** is a public, typed property with a default value and a field attribute from
  `Setono\SyliusCMSPlugin\Field`, such as `Text`, `RichText`, `Media`, `Link` or `Products`. The table of field types is
  in [Extending](../spec/extending.md#shipped-field-types). `required: true` makes publish refuse an empty value, and
  Symfony Validator constraints on the property are checked on publish too.
- **`children`** lists the block types it may hold, each as an `Allow` with an optional `min` and `max`. `new Allow('*')`
  allows any block type. Leave it out for a block that holds none.
- **`styles`** are the [block styles](styling.md#block-styles) it offers: a code, a label and the class added to its root
  element.
- The other options are `version` (see [Changing the fields later](#changing-the-fields-later)), `parents` (the only block
  types it may sit inside), `needs` (the [context keys](../spec/slots.md#context-keys) it needs, such as `['product']`)
  and `thumbnail` (a public path to the picker's preview image).

The plugin creates the class without constructor arguments and fills its properties, so it takes none.

## Registering it

Autoconfiguration registers every class with `#[BlockType]`, so a block type under `src/` needs nothing more in a
Sylius-Standard app, whose `config/services.yaml` loads `App\` with `autoconfigure: true`. Elsewhere, register the class
as a service with `autoconfigure: true`.

Add the labels to `translations/messages.en.yaml`:

```yaml
app:
    cms:
        block_type:
            hero: Hero
        block_style:
            dark: Dark
        field:
            heading: Heading
            image: Image
```

## The template

```twig
{# templates/cms/block/hero.html.twig #}
<section class="app-hero{{ view.styleClass ? ' ' ~ view.styleClass }}">
    {{ cms_image(block.image, {layout: 'full_width', priority: true}) }}
    <h2 class="app-hero__heading">{{ cms_field(block, 'heading') }}</h2>
    {% if children is not empty %}
        <div class="app-hero__buttons">{{ cms_blocks(children) }}</div>
    {% endif %}
</section>
```

A block template gets these variables:

| Variable | Value |
| --- | --- |
| `block` | The block type object, its properties holding the values in the current locale, with fallbacks and bindings resolved. |
| `view` | The `Setono\SyliusCMSPlugin\Rendering\BlockView`: `key`, `type`, `block`, `styleClass` and `children`. |
| `children` | The children that show, as `BlockView`s. `cms_blocks(children)` renders them all, `cms_block(child)` one. |
| `context` | The [host-page context](../../GLOSSARY.md#shop-pages), such as `context.product` on a product page. |

- **Print a field with `cms_field(block, 'name')`**, not `block.name`, so editors can type in it in the builder's preview.
  `cms_value(block, 'name')` reads a value without printing it, such as a list to loop over.
- **Put `view.styleClass` on the root element**, so the chosen block style applies.
- **Images** go through `cms_image()`, which renders a responsive `<img>` or `<picture>`, and nothing for an empty image
  ([The image helper](../spec/media.md#the-image-helper)).

The Twig functions are listed in [Twig](../spec/extending.md#twig).

## Changing the fields later

Blocks are stored as JSON in each entry's block tree, so a change to the fields reaches content that's already there.

- **Adding a field** needs nothing: blocks stored without it get the property's default.
- **Removing a field** needs nothing either: its stored value is ignored, and dropped on the next save.
- **Renaming a field, or changing its field type or whether it's translatable**, needs a version bump, such as
  `#[BlockType(…, version: 2)]`, and an upgrader from the old version, which rewrites each stored block as it's read:

```php
// src/CMS/Upgrade/HeroTitleToHeading.php
namespace App\CMS\Upgrade;

use App\CMS\Block\Hero;
use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * Hero 1 stored the heading as "title"; Hero 2 calls it "heading"
 */
#[AsUpgrader(of: Hero::class, from: 1)]
final class HeroTitleToHeading implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        // The values, and the translation state of a translatable field, are both keyed by field name
        foreach (['fields', 'translated'] as $map) {
            $values = $data[$map] ?? null;
            if (is_array($values) && array_key_exists('title', $values)) {
                $values['heading'] = $values['title'];
                unset($values['title']);
                $data[$map] = $values;
            }
        }

        return $data;
    }
}
```

`$data` is the block in the [stored format](../spec/content-model.md#a-block), without its children. The plugin sets the
new version after the upgrader returns. `bin/console setono:cms:upgrade` writes every upgraded block back, but it's
optional: upgraders run whenever stored content is read. When and why to bump the version is in
[Schema changes](../spec/content-model.md#schema-changes).

## More

- A test application block type with a field type of its own: `tests/Application/Block/StoreHours.php`, and one that
  needs the product of the page it's on: `tests/Application/Block/ProductNote.php`.
- To change a shipped block type's fields, register a block type of your own with its own code instead
  ([Switching off what ships](switching-off.md)).
