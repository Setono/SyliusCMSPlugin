# Adding a content type

A [content type](../../GLOSSARY.md#entries) is a kind of entry, such as Page, Snippet or Blog post. A project adds its
own as a Doctrine entity that extends `Setono\SyliusCMSPlugin\Model\Entry`, carries `#[ContentType]` and is registered
as a Sylius resource. Its mapped properties with a field attribute are its fields, which editors fill in the builder's
settings.

This guide adds store locations: each has its own URL under `/stores`, a city shared by every locale and directions in
each locale.

## The entity and its translation

```php
// src/Entity/StoreLocation.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

#[ORM\Entity]
#[ContentType(
    code: 'store_location',
    label: 'app.cms.content_type.store_location',
    prefix: 'stores',
    order: ['city' => 'ASC'],
)]
class StoreLocation extends Entry
{
    #[ORM\Column(type: 'string', nullable: true)]
    #[Text(label: 'app.cms.field.city', required: true)]
    private ?string $city = null;

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new StoreLocationTranslation();
    }
}
```

```php
// src/Entity/StoreLocationTranslation.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Field\Textarea;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;

#[ORM\Entity]
class StoreLocationTranslation extends EntryTranslation
{
    #[ORM\Column(type: 'text', nullable: true)]
    #[Textarea(label: 'app.cms.field.directions')]
    private ?string $directions = null;

    public function getDirections(): ?string
    {
        return $this->directions;
    }

    public function setDirections(?string $directions): void
    {
        $this->directions = $directions;
    }
}
```

- **Shared fields sit on the entry, translatable ones on its translation.** A content type with no translatable fields
  of its own has no translation subclass, and uses `EntryTranslation` itself.
- **Every entry already has** a title and a slug per locale, the SEO fields, its channels, its audience and, for a
  content type that declares `blocks`, a block tree.
- **Columns are nullable.** Entries share one table, and a required field is enforced by publish, not by the database.
  The plugin prefixes each of your columns with the content type's code, so `city` is stored in `store_location_city`.
- **A field's value is what Doctrine maps**, such as a UUID string for a `Media` field. Templates get it hydrated
  through `cms_value()` and `cms_field()`.

`#[ContentType]` options:

| Option | Meaning |
| --- | --- |
| `code` | The discriminator value and the column prefix. Never changes. |
| `label` | A translation key. |
| `prefix` | The URL prefix: `'stores'` gives `/{locale}/stores/{slug}`, `''` puts URLs at the root, and `null`, the default, gives no URL of its own, as a snippet has. |
| `blocks` | What the top level of its block tree may hold, such as `[new Allow('*')]`. `null`, the default, means it has no block tree. |
| `cards` | How the Entries block shows it, such as `[new Card('grid', 'cms/card/store_location/grid.html.twig')]` ([Cards](../spec/collections.md#cards)). |
| `order` | The order sources list its entries in, such as `['city' => 'ASC']`. `'position'` lets editors drag them into order in the admin list. |
| `categoryType` | The category content type of a collection with categories, such as the blog's ([Collections](../spec/collections.md)). |
| `descriptionField` | A translatable property the meta description falls back to. |
| `version` | Bumped with an upgrader when stored entries can't be read as they are, as for a [block type](block-types.md#changing-the-fields-later). |

## Registering it

Register the entity and its translation as a Sylius resource, in `config/packages/sylius_resource.yaml`:

```yaml
sylius_resource:
    resources:
        app.store_location:
            classes:
                model: App\Entity\StoreLocation
                factory: Sylius\Resource\Factory\TranslatableFactory
            translation:
                classes:
                    model: App\Entity\StoreLocationTranslation
```

For a content type without translatable fields of its own, the translation model is
`Setono\SyliusCMSPlugin\Model\EntryTranslation`. The plugin finds every resource whose model is an entry, and fills
both discriminator maps from them. Sylius-Standard maps `App\Entity` with attributes already.

Add the labels to `translations/messages.en.yaml`, with the plural that the admin's lists and the Content hub name the
content type by:

```yaml
app:
    cms:
        content_type:
            store_location: Store location
        field:
            city: City
            directions: Directions
        layout:
            store: Store

setono_sylius_cms:
    content_type_plural:
        store_location: Store locations
```

Then generate a migration for the new columns (`bin/console doctrine:migrations:diff`), and review it before you run
it.

## A layout

A content type with its own URL needs a default layout, or the container fails to build:

```php
// src/CMS/Layout/Store.php
namespace App\CMS\Layout;

use App\Entity\StoreLocation;
use Setono\SyliusCMSPlugin\Attribute\Layout;

#[Layout(
    code: 'store',
    contentType: StoreLocation::class,
    label: 'app.cms.layout.store',
    template: 'cms/layout/store.html.twig',
    default: true,
    shows: ['title', 'city', 'directions'],
)]
final class Store
{
}
```

```twig
{# templates/cms/layout/store.html.twig #}
{% extends '@SetonoSyliusCMSPlugin/layout/base.html.twig' %}

{% block cms_content %}
    <article class="app-store">
        <h1>{{ cms_field(entry, 'title') }}</h1>
        <p class="app-store__city">{{ cms_field(entry, 'city') }}</p>
        <p class="app-store__directions">{{ cms_field(entry, 'directions') }}</p>
    </article>
{% endblock %}
```

See [Layouts](layouts.md) for the rest. Store locations now have an item in the admin's Content hub, a list, the create
dialog and URLs such as `/en_US/stores/copenhagen`. A project moves the URLs with
`setono_sylius_cms.content_types.store_location.prefix`.

## Extending a shipped content type

To add a field to Page, Blog post or another shipped content type, subclass its model, and its translation model when
the field is translatable, and override them in the plugin's own resources config. The subclass keeps the shipped
`#[ContentType]`, so its entries are still pages.

```php
// src/Entity/Page.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Page as BasePage;

#[ORM\Entity]
class Page extends BasePage
{
    /** Shared by every locale, in the column page_campaign_code */
    #[ORM\Column(type: 'string', nullable: true)]
    #[Text(label: 'app.cms.field.campaign_code')]
    private ?string $campaignCode = null;

    public function getCampaignCode(): ?string
    {
        return $this->campaignCode;
    }

    public function setCampaignCode(?string $campaignCode): void
    {
        $this->campaignCode = $campaignCode;
    }
}
```

```yaml
# config/packages/setono_sylius_cms.yaml
setono_sylius_cms:
    resources:
        page:
            classes:
                model: App\Entity\Page
```

The shipped models, their translation models and resource aliases are in
[Shipped content types](../spec/extending.md#shipped-content-types).

## More

- The test application's store locations: `tests/Application/Entity/StoreLocation.php`, registered in
  `tests/Application/config/packages/sylius_resource.yaml`.
- `setono_sylius_cms.disabled.content_types` switches off the Blog and the FAQ
  ([Switching off what ships](switching-off.md)).
