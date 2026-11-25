# Extending

The developer-facing API: attributes, field types, interfaces, events, Twig functions, routes, config and console commands. Everything in this chapter is a public contract.

- **Terms:** the whole [glossary](../../GLOSSARY.md).
- **Decisions:** [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (extension points are classes whose typed properties are their fields), [ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md) (sources return a Pagerfanta adapter), [ADR 0004](../adr/0004-slots-have-platform-neutral-identifiers.md) (slot identifiers), [ADR 0006](../adr/0006-bindings-use-declared-properties.md) (declared bindable properties), [ADR 0014](../adr/0014-images-are-processed-by-liip-behind-an-image-provider.md) (image provider), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (rendering on every request).

## The model

Everything a developer adds is a PHP class registered by attribute: block types, layouts, content types, sources, condition kinds, bindable properties, link targets, context resolvers and upgraders. Where one of these has settings that editors fill in, each setting is a typed property with a [field attribute](#field-types). Symfony Validator constraints sit on the same properties.

- **Discovery.** Classes with these attributes are registered through Symfony autoconfiguration, so in an app they only need to be under a resource the app's `services.yaml` loads. A bundle registers them as services. Block types and layouts are registered as metadata, not as shared services, because they're hydrated per use.
- **Services.** Sources, condition kinds, bindable properties, link targets, context resolvers, upgraders and field type handlers are services and can take constructor dependencies.
- **Parameters.** A source, condition kind or bindable property with parameters names a parameters class in its attribute (`params:`). That class's typed properties carry field attributes, and the plugin passes a hydrated instance to the service's method. Without `params:`, the method gets an instance of `Setono\SyliusCMSPlugin\Attribute\NoParams`.
- **Codes are unique per kind.** Two block types with the same code fail the container build, and so on for every kind. Codes match `^[a-z][a-z0-9_]*$` and never change once content uses them. Projects should prefix theirs, such as `app_hero`.
- **Labels** are translation keys, translated in the admin user's locale.

Class-level attributes live in `Setono\SyliusCMSPlugin\Attribute`, field attributes in `Setono\SyliusCMSPlugin\Field`, and the interfaces below in the namespaces their headings name.

## Block types

```php
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[BlockType(
    code: 'app_hero',
    label: 'app.cms.block_type.hero',
    version: 1,
    template: 'cms/block/hero.html.twig',
    children: [new Allow(Button::class, max: 2)],
    styles: [new Style('dark', 'app.cms.block_style.dark', 'app-hero--dark')],
)]
final class Hero
{
    #[Text(label: 'app.cms.field.heading')]
    #[Assert\NotBlank]
    public ?string $heading = null;

    #[Media(label: 'app.cms.field.image', kinds: ['image'])]
    public ?MediaItemInterface $image = null;
}
```

`#[BlockType]` options:

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `code` | `string` | required | Stored in each block's `type`. |
| `label` | `string` | required | Shown in the picker and the tree. |
| `version` | `int` | `1` | Bumped with an upgrader when stored data can't be read as it is ([Content model](content-model.md#schema-changes)). |
| `template` | `string` | required | The Twig template that renders the block ([Block templates](#block-templates)). |
| `children` | `list<Allow>` | `[]` | Which block types the block may hold. Empty means none. |
| `parents` | `list<class-string>\|null` | `null` | The only block types this one may sit inside. `null` means anywhere a parent allows it. |
| `needs` | `list<string>` | `[]` | The context keys the block type needs ([Slots](slots.md#context-keys)). |
| `styles` | `list<Style>` | `[]` | The block styles it offers. Projects add more in config. |
| `thumbnail` | `string\|null` | `null` | A public path to the picker's preview image. |

- `Allow(string $type, ?int $min = null, ?int $max = null)`: `$type` is a block type class, or `'*'` for any block type whose `parents` allows it.
- `Style(string $code, string $label, string $class)`: the code is stored in the block's `style`, and `$class` is added to the block's root element.

A block type class has a public, typed property for each field, with a default value. The plugin creates it without calling a constructor with arguments, so it takes none.

### Block templates

A block template gets these variables:

| Variable | Value |
| --- | --- |
| `block` | The hydrated block type object. Its properties hold hydrated values in the current locale, with fallbacks and bindings resolved. |
| `view` | The `Setono\SyliusCMSPlugin\Rendering\BlockView`: `key`, `type` (the code), `block`, `styleClass` (the chosen style's class, or `null`) and `children`. |
| `children` | `view.children`: only the children whose visibility conditions match, as `BlockView`s. Render one with `cms_block(child)` or all with `cms_blocks(children)`. |
| `context` | The host-page context, keyed by context key, such as `context.product`. |

Templates print fields with `cms_field()` so the builder can type them in place ([Twig](#twig)). They put `view.styleClass` on their root element.

A per-block-type renderer may come with a future sandboxed code block, which would render a template stored in one of its fields through Twig's sandbox.

## Layouts

```php
use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

#[Layout(
    code: 'landing',
    contentType: Page::class,
    label: 'app.cms.layout.landing',
    thumbnail: '/build/app/cms/landing.svg',
    template: 'cms/layout/landing.html.twig',
    version: 1,
    default: false,
    shows: ['title'],
    slots: ['page.landing.after_hero'],
)]
final class Landing
{
    #[Toggle(label: 'app.cms.field.transparent_header')]
    public bool $transparentHeader = false;
}
```

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `code` | `string` | required | Unique within its content type. Stored in the tree's `layout.code`. |
| `contentType` | `class-string` | required | The content type it belongs to, which must have its own URL. |
| `label` | `string` | required | |
| `thumbnail` | `string\|null` | `null` | The wireframe card in the create dialog and the entry settings. |
| `template` | `string` | required | |
| `version` | `int` | `1` | Versions its fields, like a block type's. |
| `default` | `bool` | `false` | Marks the content type's default layout. Two defaults for one content type fail the container build, unless `content_types.<code>.default_layout` names one, which always wins ([Configuration](#configuration)). |
| `shows` | `list<string>` | `[]` | The entry fields its template prints, by property name. The builder marks the others "Not shown in this layout". |
| `slots` | `list<string>` | `[]` | The slots its template renders with `cms_slot()`, by identifier. A snippet placed in one previews on the page of an entry in this layout ([Builder](builder.md#snippet-preview)). |

Layout fields are the class's typed properties, stored in the tree's `layout.fields` ([Layouts and presets](layouts-and-presets.md)).

### Layout templates

A layout template extends `@SetonoSyliusCMSPlugin/layout/base.html.twig`. That template extends `@SyliusShop/layout.html.twig`, fills its `title` block with `cms_meta_title(entry)` and its `metatags` block with `cms_head_tags(entry)` ([URLs and SEO](urls-and-seo.md#head-tags), [Twig](#twig)), and renders the block `cms_content` inside `content`. A layout that overrides those blocks calls both. A layout template gets:

| Variable | Value |
| --- | --- |
| `entry` | The entry being shown. |
| `layout` | The hydrated layout object. |
| `blocks` | The visible top-level blocks, as `BlockView`s. Render them with `cms_blocks(blocks)`. |
| `context` | The host-page context: `{entry: entry}`. |

A layout renders its own slots with `cms_slot()`.

## Content types

A content type is a Doctrine entity that extends `Setono\SyliusCMSPlugin\Model\Entry`, registered as a Sylius resource, plus `#[ContentType]`:

```php
use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Entry;

#[ORM\Entity]
#[ContentType(
    code: 'store_location',
    label: 'app.cms.content_type.store_location',
    version: 1,
    prefix: 'stores',
    blocks: [new Allow('*')],
    cards: [new Card('grid', 'cms/card/store_location/grid.html.twig')],
    order: ['city' => 'ASC'],
)]
class StoreLocation extends Entry
{
    #[ORM\Column(type: 'string', nullable: true)]
    #[Text(label: 'app.cms.field.city')]
    private ?string $city = null;
}
```

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `code` | `string` | required | The discriminator value and the column prefix. Never changes. |
| `label` | `string` | required | |
| `version` | `int` | `1` | Versions its fields in drafts, revisions and exports. |
| `prefix` | `string\|null` | `null` | The URL prefix. `null` means the content type has no URL of its own, and `''` means its URLs sit at the root. Projects change it in config. |
| `blocks` | `list<Allow>\|null` | `null` | What the top level of its tree may hold. `null` means it has no block tree. |
| `cards` | `list<Card>` | `[]` | `Card(string $display, string $template)`. The displays are `grid`, `carousel`, `accordion`, or a project's own. |
| `order` | `array<string, 'ASC'\|'DESC'>\|'position'` | `['createdAt' => 'DESC']` | The order sources list its entries in. `'position'` lets editors drag entries into order in the admin list. |
| `categoryType` | `class-string\|null` | `null` | The category content type of its collection. The content type must then have one `Entries` field with `contentType` set to it and `indexed: true`. |
| `descriptionField` | `string\|null` | `null` | The property the meta description falls back to. |

- **Registration.** Register it with `#[AsResource]` when it has no translatable fields of its own. With a translation subclass, register it under `sylius_resource.resources`, because `#[AsResource]` has no translation option:

  ```yaml
  sylius_resource:
      resources:
          app.store_location:
              classes:
                  model: App\Entity\StoreLocation
              translation:
                  classes:
                      model: App\Entity\StoreLocationTranslation
  ```

  A translation subclass extends `Setono\SyliusCMSPlugin\Model\EntryTranslation`. A content type without one uses `EntryTranslation` itself.
- **Fields.** Mapped properties with a field attribute. Shared fields sit on the entry subclass, and translatable fields on its translation subclass. The property holds the value as Doctrine maps it, such as a UUID string for a `Media` field. `cms_value()` hydrates it.
- **Columns.** The plugin prefixes every column of a subclass, and of its translation subclass, with the content type's code and `_`, such as `blog_post_published_at`. It builds both discriminator maps from the registered content types.
- **Extending a shipped content type.** Subclass its model, and its translation model if needed, and override them in the plugin's `resources` config, the way Sylius plugins let projects replace their models. The subclass keeps the shipped `#[ContentType]`: the plugin reads the attribute from the nearest class in the hierarchy that has one.

  ```yaml
  setono_sylius_cms:
      resources:
          page:
              classes:
                  model: App\Entity\Page
              translation:
                  classes:
                      model: App\Entity\PageTranslation
  ```
- **Disabling a shipped content type** with `disabled.content_types` hides it from the admin, the builder, sources and routing. Its columns stay mapped. A collection is switched off as a whole: its entries' content type with the category content type it names, or the build fails.

### Shipped content types

| Code | Model | Translation model | Resource alias |
| --- | --- | --- | --- |
| `page` | `Setono\SyliusCMSPlugin\Model\Page` | `PageTranslation` | `setono_sylius_cms.page` |
| `snippet` | `Snippet` | `EntryTranslation` | `setono_sylius_cms.snippet` |
| `blog_post` | `BlogPost` | `BlogPostTranslation` | `setono_sylius_cms.blog_post` |
| `blog_category` | `BlogCategory` | `BlogCategoryTranslation` | `setono_sylius_cms.blog_category` |
| `faq_item` | `FaqItem` | `FaqItemTranslation` | `setono_sylius_cms.faq_item` |
| `faq_category` | `FaqCategory` | `EntryTranslation` | `setono_sylius_cms.faq_category` |

Each model has an interface beside it, such as `PageInterface`. `page` and `snippet` can't be disabled.

## Field types

A field type is a pair, like a Symfony constraint and its validator: an attribute class that carries its typed options, and a handler service.

### Shipped field types

Every field attribute extends `Setono\SyliusCMSPlugin\Field\Field`, which has these options:

| Option | Type | Default |
| --- | --- | --- |
| `label` | `string` | required |
| `help` | `string\|null` | `null` |
| `required` | `bool` | `false` |
| `translatable` | `bool\|null` | `null`, meaning the field type's default |

| Attribute | Code | Translatable by default | Bindable | Hydrated as | Own options |
| --- | --- | --- | --- | --- | --- |
| `Text` | `text` | yes | text, number | `?string` | `maxLength: ?int` |
| `Textarea` | `textarea` | yes | text, number | `?string` | `maxLength: ?int` |
| `RichText` | `rich_text` | yes | tokens: text, number | `?Setono\SyliusCMSPlugin\Value\RichText` | |
| `Number` | `number` | no | number | `int\|float\|null` | `min`, `max`, `step` (`int\|float\|null`), `integer: bool = false` |
| `Toggle` | `toggle` | no | | `bool` | |
| `Choice` | `choice` | no | | `?string`, or `list<string>` | `choices: array<string, string>` (value to label), `choicesProvider: class-string<ChoicesProviderInterface>\|null = null`, `multiple: bool = false` |
| `Date` | `date` | no | | `?DateTimeImmutable` | |
| `DateTime` | `date_time` | no | | `?DateTimeImmutable` | |
| `Link` | `link` | label and URL only | url | `?Setono\SyliusCMSPlugin\Value\Link` | `targets: list<string>\|null = null` (link target codes, `null` for all) |
| `Media` | `media` | no | | `?MediaItemInterface` | `kinds: list<'image'\|'video'\|'document'> = ['image']` |
| `MediaList` | `media_list` | no | | `ItemList<MediaItemInterface>` | `kinds`, `max: ?int` |
| `Product` | `product` | no | | `?ProductInterface` | |
| `Products` | `products` | no | | `ItemList<ProductInterface>` | `max: ?int`, `sources: bool\|list<string> = true`, `limit: int = 8` |
| `Taxon` | `taxon` | no | | `?TaxonInterface` | |
| `Taxons` | `taxons` | no | | `ItemList<TaxonInterface>` | `max`, `sources`, `limit` |
| `Entry` | `entry` | no | | `?EntryInterface` | `contentType: class-string\|null` |
| `Entries` | `entries` | no | | `ItemList<EntryInterface>` | `contentType: class-string\|null`, `categoriesOf: string\|null = null`, `indexed: bool = false`, `max`, `sources`, `limit` |
| `Snippet` | `snippet` | no | | `?SnippetInterface` | |
| `Code` | `code` | no | | `?string` | |

- **List fields.** `sources: true` offers every source of the right kind, a list offers only those codes, and `false` allows only hand-picking. `limit` is the default number of items shown when a source fills the list. A source's own parameter fields are always hand-picked.
- **Dynamic choices.** A `Choice` field whose choices come from the store, such as the association types of the Associations source, names a service implementing `Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface`, whose `getChoices(string $localeCode): array` returns value to label. It's used instead of `choices`. A value the provider no longer gives doesn't fit the field, so publish refuses it, even the code of a channel, locale or customer group the store no longer has. Only a block's visibility condition keeps one of those, and the admin flags it ([Targeting](targeting.md#deleted-records-in-conditions)).
- **`ItemList`** (`Setono\SyliusCMSPlugin\Value\ItemList`) is iterable and countable over the items that show, resolved lazily on first read. `adapter()` returns the Pagerfanta adapter, and `pager(int $page)` returns a `Pagerfanta` for the paginated Entries block.
- **`contentType`** on `Entry` and `Entries` names a content type by its model, or by a class or interface its model extends, so it still names a shipped content type whose model a project overrides, such as `BlogCategory::class`. The builder's picker searches only that content type's entries, and a picked entry of another content type isn't shown.
- **`Entries(indexed: true)`** writes the field's UUIDs to the (entry, field, UUID) reference table on publish ([Collections](collections.md#categories)). Only a content type's own shared field is indexed: `indexed: true` on a block type's, a layout's or a translatable field, or among a source's parameters, fails the container build, since the table holds a UUID by its entry and field.
- **No colour, spacing or icon fields** ([ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md)), and no repeater ([ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md)).

The stored value of each type is in [Content model](content-model.md#values-per-field-type).

### Adding a field type

A field attribute class extends `Field` and carries `#[FieldType]`, which declares what the plugin needs before it stores or shows a value:

```php
use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_opening_hours', translatable: false, bindable: [], control: 'app-opening-hours')]
final class OpeningHours extends Field
{
}
```

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `code` | `string` | required | The field type's code. Unique, and never changes once content uses it. |
| `translatable` | `bool` | `false` | Its default translatability, used when a field leaves `translatable:` at `null`. It decides whether the plugin stores one value or a locale map of values. A field type can still keep locale maps inside its one value: Link declares `false` and keeps its label per locale, and its handler reads the field's own `translatable` to pick the [link's shape](content-model.md#links). |
| `bindable` | `list<BindingType>` | `[]` | The binding types a field of this type accepts. Empty means it can't be bound. |
| `control` | `string\|null` | `null` | The custom element's tag name ([Field controls](#field-controls)), such as `app-opening-hours`: lowercase, starting with a letter, with a hyphen in it. Required for a project's own field type. The shipped field types have none. |

The shipped field types carry it too, with the codes, defaults and binding types in the table above. A field attribute class that extends another inherits its field type, as the list fields share theirs. The container build fails on two field types with one code, a code that doesn't match `^[a-z][a-z0-9_]*$`, a project's own field type without a valid `control`, and a field attribute without `#[FieldType]`. It checks the shipped field types and those the fields of block types, layouts, content types, and the parameters of condition kinds and sources use.

```php
namespace Setono\SyliusCMSPlugin\Field;

abstract class Field
{
    /** The handler's service id. Defaults to the attribute's class name plus "Handler". */
    public function handledBy(): string;
}

interface FieldHandlerInterface
{
    /** JSON Schema 2020-12 for one value (one locale's value, for a translatable field), plus x-cms-* keywords. */
    public function schema(Field $field): array;

    /** Turns a stored value into what templates get. Called lazily. */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): mixed;

    /** @return iterable<Reference> */
    public function references(Field $field, mixed $value): iterable;

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void;
}
```

`Reference` (`Setono\SyliusCMSPlugin\Field\Reference`) has `type` (`product`, `taxon`, `channel`, `customer_group`, `locale`, `product_attribute`, `entry` or `media_item`), `id` (a code or a UUID) and `dependency` (`true` when an export must bring the record along). Snippets and media items in a block tree are dependencies, and entries, products and taxons are links.

The schema's `x-cms-*` keywords. The plugin adds most of them around what the handler's `schema()` returns:

| Keyword | Value | Filled by |
| --- | --- | --- |
| `x-cms-type` | The field type's code. | the plugin, from `#[FieldType(code:)]` |
| `x-cms-control` | The custom element's tag name, for a project's own field type. | the plugin, from `#[FieldType(control:)]` |
| `x-cms-label`, `x-cms-help` | Translated strings. | the plugin, from the field's `label` and `help` |
| `x-cms-required` | `true` or `false`. | the plugin, from the field's `required` |
| `x-cms-translatable` | `true` or `false`. | the plugin, from the field's `translatable`, or the field type's default |
| `x-cms-bindable` | The binding types the field accepts (`text`, `number`, `url`), or absent. | the plugin, from `#[FieldType(bindable:)]` |
| `x-cms-binds-inside` | `true` when the field holds its bindings inside its values, as a Link's `ref` and rich text's tokens do, rather than as its whole value; absent otherwise. | the plugin, when the handler implements `InnerBindingFieldHandlerInterface` |
| `x-cms-options` | The field type's own options, as JSON. | the handler |

Everything else in the schema, the JSON Schema for one value, is the handler's.

### Field controls

A project's own field type comes with a [field control](../../GLOSSARY.md#content-structure): a custom element, loaded from a script listed under `setono_sylius_cms.builder.scripts`. Each script is resolved through Symfony's `asset()`, so a project whose build versions its files, with a JSON manifest, lists the asset path, such as `build/app/cms/controls.js`, and the builder loads the built file. A block type built only from shipped field types needs no JS.

- The builder sets four properties on the element: `.value` (the stored value, for the current locale when the field is translatable, or `null` when it has none), `.field` (its schema), `.locale` (the locale being edited) and `.builder`. It sets `.field`, `.locale` and `.builder` before `.value`, and each again only when it changes.
- `.builder` has `search(kind, query)`, which resolves to `[{ref, name, thumbnail}]` for `product`, `taxon`, `entry`, `media` or a link target's code, `pickMedia({kinds, multiple})`, which resolves to UUIDs or `null`, and `trans(key, params)`.
  - `pickMedia()` opens the media library as a picker, as a Media field does. `kinds` defaults to `['image']` and `multiple` to `false`, and it resolves to `null` when the editor cancels.
  - `trans()` translates the builder's own UI strings, the `setono_sylius_cms.builder.*` keys, filling in placeholders such as `{'%count%': 3}`, and gives back the key it doesn't know. A field type's own strings reach its field control translated, in `x-cms-options`, from its handler's `schema()`, which can inject the translator.
- The field control dispatches a bubbling `cms-change` `CustomEvent` with `detail.value` whenever its value changes. `null`, `''`, `[]` or no `value` removes it. The builder doesn't set a value back on the element that just gave it, so the element keeps its own state, such as the focus.
- The builder renders the element as a group its field's label names, and creates it once its custom element is defined. Scripts load as ES modules before the builder app, in the order they're listed.
- The builder's store freezes the values it keeps, so the element gets a copy in `.value` and the builder keeps a copy of `detail.value`.

## Sources

```php
namespace Setono\SyliusCMSPlugin\Source;

use Pagerfanta\Adapter\AdapterInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

interface SourceInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface;
}
```

`#[AsSource(code, label, of, needs = [], params = null)]`:

| Option | Meaning |
| --- | --- |
| `code`, `label` | |
| `of` | What it lists: `ProductInterface::class`, `TaxonInterface::class`, `EntryInterface::class` for entries of any content type, or one content type's class. |
| `needs` | The context keys it needs. It's offered only where they're available. |
| `params` | Its parameters class. |

A source that needs `entry` and fits only some content types also implements `EntryContextAwareInterface::supportsEntryContentType(string $code): bool`. The builder offers it only for those types, publish refuses it in an entry of another, and a preset holding it is offered only in entries of them. A snippet's entry can be of any content type, so a snippet may hold it. "In this category" and "Related" do this.

An entry source whose `of` is `EntryInterface::class` can say which content type it lists, so the Entries block can offer that type's [card displays](collections.md#the-entries-block):

```php
namespace Setono\SyliusCMSPlugin\Source;

interface ListedContentTypeAwareInterface
{
    /** The code of the content type it lists with these parameters, in an entry of $hostContentType (null outside an entry). Null when it can't tell. */
    public function getListedContentType(object $params, ?string $hostContentType): ?string;
}
```

Without it, or when it returns `null`, the listed type is the one `of` names. When that's `EntryInterface::class` too, the block can't tell, and offers every display any content type has cards for. All three shipped entry sources implement it.

**A field that depends on another.** Field options are fixed in code, with one exception: `Entries(categoriesOf: 'contentType')` lists the categories of the content type that the named property of the same object holds. From a collection's `categories` parameter uses it. The builder clears the field when that property changes.

The rules every source follows are in [Sources](sources.md): a stable order ending in a unique tie-breaker, no limit of its own, and building on the visible queries:

```php
namespace Setono\SyliusCMSPlugin\Query;

interface VisibleEntriesQueryInterface
{
    /** Root alias "entry". No ORDER BY. */
    public function createQueryBuilder(string $contentType, RenderContext $context): QueryBuilder;
}

interface VisibleProductsQueryInterface
{
    /** Root alias "product". No ORDER BY. */
    public function createQueryBuilder(RenderContext $context): QueryBuilder;
}

interface VisibleTaxonsQueryInterface
{
    /** Root alias "taxon". No ORDER BY. */
    public function createQueryBuilder(RenderContext $context): QueryBuilder;
}
```

## Condition kinds

```php
namespace Setono\SyliusCMSPlugin\Condition;

interface ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool;
}

interface TimeDependentConditionKindInterface extends ConditionKindInterface
{
    /** The next time the result can change after $now, or null. */
    public function nextChange(object $params, \DateTimeImmutable $now): ?\DateTimeImmutable;
}
```

`#[AsConditionKind(code, label, needs = [], reads = null, params = null, matches = 'setono_sylius_cms.builder.is_one_of', matchesNot = 'setono_sylius_cms.builder.is_not_one_of')]`. `reads` is a list of `Setono\SyliusCMSPlugin\Condition\Reads` cases: `HostPage`, `Channel`, `Locale`, `Customer` and `Time`. `null`, the default, means unknown, and a later cache treats a kind that hasn't declared what it reads as uncacheable ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)). `[]` claims it reads nothing. A kind that reads `Time` implements `TimeDependentConditionKindInterface`.

`matches` and `matchesNot` are optional translation keys for how the builder words a condition and its negation: "is one of" and "is not one of". A kind that matches otherwise words them its own way, as the shipped date window does with "is inside" and "is outside".

Publish checks a condition's parameter values by their fields, and then together against the Symfony Validator constraints on the parameters class, such as `Setono\SyliusCMSPlugin\Validation\Constraint\EndsAfterStart`, which the shipped date window's parameters carry: `#[EndsAfterStart(start: 'from', end: 'until')]` refuses an end that doesn't come after its start, at the end field ([Publishing](publishing.md#validation)). A parameter that picks from the store's channels, locales or customer groups, as the channel, locale and customer group kinds' do, keeps one the store no longer has in a block's visibility condition, where the admin flags it, and refuses it in a page scope ([Targeting](targeting.md#deleted-records-in-conditions)).

`matches()` answers the condition. The plugin provides the negation by negating it, so kinds never implement it. It's asked only when the render context holds every context key the kind `needs`: without one, the condition never matches, negated or not ([Targeting](targeting.md#condition-kinds)). Kinds work in visibility conditions on blocks and in page scopes, never on entries ([ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md)). The shipped kinds are in [Targeting](targeting.md#condition-kinds).

## Bindable properties

```php
namespace Setono\SyliusCMSPlugin\Binding;

interface BindablePropertyInterface
{
    public function resolve(object $subject, object $params, RenderContext $context): string|int|float|null;
}
```

It reads the channel, locale, currency and customer from `$context`, like every other extension point ([Rendering](rendering.md)). `#[AsBindableProperty(contextKey, code, label, type, params = null)]`. `contextKey` is a context key, `channel` or `customer`. `type` is a `Setono\SyliusCMSPlugin\Binding\BindingType` case: `Text`, `Number` or `Url`. A binding is stored as `<contextKey>.<code>`.

A property that's a plain property path can be declared in config instead:

```yaml
setono_sylius_cms:
    bindable_properties:
        product:
            brand: { label: 'app.cms.binding.brand', type: text, path: 'brand.name' }
```

The shipped properties are in [Slots](slots.md#bindings).

## Link targets

```php
namespace Setono\SyliusCMSPlugin\Link;

interface LinkTargetInterface
{
    /** null when the reference doesn't resolve, or its record isn't visible: the link isn't rendered and is flagged. */
    public function resolve(string $ref, RenderContext $context): ?ResolvedLink;

    /** @return iterable<array{ref: string, name: string, thumbnail: ?string}> */
    public function search(string $query, string $localeCode): iterable;
}
```

`#[AsLinkTarget(code, label, translatableRef = false)]`. A target with `translatableRef: true`, such as `url`, stores its `ref` per locale ([Content model](content-model.md#links)). Either way, the plugin picks the current locale's ref, with the fallback chain, so `resolve()` always gets one string. `ResolvedLink` has `target` and `ref` (the reference it resolved, so a later API can give a headless frontend both), `url` and `name`. An empty label shows `name`, the target's current name in the shopper's locale. Any target can add an anchor.

Shipped targets:

The `entry`, `product` and `taxon` targets lead to relative paths, such as `/en_US/products/red-t-shirt`, as Sylius's templates link with `path()`. Only an entry's canonical and hreflang links are absolute URLs ([URLs and SEO](urls-and-seo.md#head-tags)).

| Code | Refers to | Resolves to |
| --- | --- | --- |
| `entry` | an entry with its own URL, by UUID | its path in the current channel and locale, `/{locale}/` for the channel's homepage, or `null` when it isn't visible |
| `product` | a product, by code | its page's path in the shop, or `null` when it isn't visible ([Sources](sources.md#visible-queries)) |
| `taxon` | a taxon, by code | its product listing's path in the shop, or `null` when it isn't enabled |
| `media` | a document or video media item, by UUID | its permanent URL ([Media](media.md#permanent-urls)) |
| `url` | a URL, per locale (`translatableRef: true`): `http://` or `https://`, `mailto:`, `tel:`, a path (`/…`), a query (`?…`) or an anchor (`#…`) | the URL as typed, when it's one of those. Any other scheme, such as `javascript:`, resolves to `null`, so the link doesn't render, and publish refuses it, in a Link field and in rich text |

## Context keys and resolvers

Context keys and slots are data, so they're declared in bundle config ([Configuration](#configuration)). The plugin declares `product`, `taxon` and `entry` ([Slots](slots.md#context-keys)).

```php
namespace Setono\SyliusCMSPlugin\Context;

interface ContextResolverInterface
{
    /** The object for this key on the page being shown, or null on pages that don't have one. */
    public function resolve(Request $request): ?object;
}
```

`#[AsContextResolver(key)]` registers a resolver for one context key. A slot runs it when its template event doesn't pass that key. `$request` is always the main request (`RequestStack::getMainRequest()`), also when the slot renders inside a sub-request, such as Sylius's taxon header ([Rendering](rendering.md#preview-mode-and-share-links)).

## Upgraders

```php
namespace Setono\SyliusCMSPlugin\Upgrade;

interface UpgraderInterface
{
    public function upgrade(array $data): array;
}
```

`#[AsUpgrader(of, from)]`: `of` is a block type, layout or content type class, and `from` the version it upgrades from, to `from + 1`. Each upgrader is one step.

- For a block type, `$data` is the block's array in the [stored format](content-model.md#a-block), without `children`.
- For a layout, it's the tree's `layout` array.
- For a content type, it's the [entry format](content-model.md#entry-format) without `tree`.

The plugin sets the new version after the upgrader returns. Upgraders run whenever stored data is read: rows, drafts, revisions, presets and export files.

## Images

```php
namespace Setono\SyliusCMSPlugin\Media;

interface ImageProviderInterface
{
    /** Builds the URL without calling the storage. */
    public function url(MediaItemInterface $mediaItem, ImageTransform $transform): string;
}
```

`ImageTransform` holds `width` (`int`), `aspectRatio` (`?float`, width divided by height), `format` (`'webp'`, `'avif'` or `'original'`) and `focalPoint` (`x` and `y` from 0 to 1). The default provider uses LiipImagine ([Media](media.md#image-processing)). A project swaps it for an image CDN by aliasing the interface to its own service.

`cms_image()` offers a format, such as AVIF, only where `Setono\SyliusCMSPlugin\Media\ImageFormatsInterface::supports(ImageFormat $format): bool` says the images are served in it. The default answers WebP and the original format always, and AVIF where LiipImagine's image library writes it, so a project with an image CDN aliases this interface too.

```php
namespace Setono\SyliusCMSPlugin\Media;

interface MediaUrlGeneratorInterface
{
    /** The public URL of a file in the media storage, by its storage path. Builds it without calling the storage. */
    public function generate(string $path): string;
}
```

It gives the URLs of media items' files, which the permanent URLs redirect to and `<video>` plays, and of the image variants cached beside them, which the default image provider and Liip's resolve step hand out ([Media](media.md#image-processing)). The default builds `/media/cms/{path}`, where the default local storage is served. A project whose storage is elsewhere, such as on S3, aliases the interface to its own service.

## Bestsellers

```php
namespace Setono\SyliusCMSPlugin\Bestsellers;

interface BestsellersCalculatorInterface
{
    /** @return iterable<string, int|float> product code => score; higher sells better */
    public function calculate(ChannelInterface $channel): iterable;
}
```

`setono:cms:bestsellers:compute` calls it per channel and stores the scores. The default, `CompletedOrdersCalculator`, counts the orders whose checkout completed inside `bestsellers.window`, whatever their payment state. A project with analytics data aliases the interface to its own calculator, which can build on the default's public `createQueryBuilder(channel)` ([Sources](sources.md#bestsellers)).

## Rendering services

Placements, URL lookups and reference loading each sit behind one interface, so a project can add a cache by decorating it and invalidating on [`StorefrontChangedInterface`](#events) events ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)):

```php
namespace Setono\SyliusCMSPlugin\Rendering;

interface PlacementProviderInterface
{
    /**
     * All of the channel's placements, in slot and position order.
     *
     * @return list<PlacementData>
     */
    public function getPlacements(ChannelInterface $channel): array;
}

interface EntryUrlLookupInterface
{
    /** $path is "{prefix}/{slug}", or the slug alone for an empty prefix. Includes old slugs. */
    public function lookup(ChannelInterface $channel, string $localeCode, string $path): ?EntryUrlMatch;
}

interface ReferenceLoaderInterface
{
    /** @param iterable<Reference> $references Loads them with one query per type. */
    public function load(iterable $references, RenderContext $context): ReferenceMap;
}
```

A snippet's preview and share links show it on the host page of one of its placements ([Builder](builder.md#snippet-preview)). The plugin knows the pages of the stock context keys' objects and the slots of the shop's layout. A project decorates `HostPageUrlGeneratorInterface` for a slot of its own whose page it knows, such as a store locator's:

```php
namespace Setono\SyliusCMSPlugin\Preview;

interface HostPageUrlGeneratorInterface
{
    /** An absolute URL of a page that renders the slot, with the sample object, or null for a plain page. */
    public function generate(SlotMetadata $slot, ?object $sample, ChannelInterface $channel, string $localeCode): ?string;
}
```

`PlacementData` is a serializable value object, so a decorator can cache it: the placement's UUID, the snippet's UUID, the slot identifier, the page scope's conditions and the position. `EntryUrlMatch` has the entry's UUID and content type code, and `redirectTo`: the current path when `$path` is an old slug, otherwise `null`.

`RenderContext` (`Setono\SyliusCMSPlugin\Rendering\RenderContext`) carries the channel, the locale code, the currency code, the customer (or `null`), the customer group value (a group code, `@guest` or `@no_group`), the current time, the host-page context (`get(string $key): ?object`) and its preview mode: `null`, `builder` or `share` ([Rendering](rendering.md#preview-mode-and-share-links)). Preview mode's Shown to and Date set the customer group value and the time here, and nowhere else.

## Translation state

No translator ships in v2. The `Setono\SyliusCMSPlugin\Translation\TranslationStateInterface` service is the seam a future one fills:

```php
interface TranslationStateInterface
{
    /** Every translatable value of the entry's draft for $localeCode, with its state, source locale, source value and source fingerprint. */
    public function values(EntryInterface $entry, string $localeCode): iterable;

    /** Writes a translated value into the draft, recording its source locale and $sourceFingerprint: the fingerprint of the source value that was actually translated. */
    public function write(EntryInterface $entry, string $localeCode, string $path, mixed $value, string $sourceLocaleCode, string $sourceFingerprint): void;
}
```

Each value has a `path` (`entry.<field>`, `layout.<field>` or `block.<key>.<field>`), a `state` (`Untranslated`, `Outdated` or `UpToDate`), the source locale, the source value, the source's current fingerprint and the current value. A Link field of the default shape is shared, but its label, and the URL of a target whose ref is translatable, are per locale: its value in a locale is `{"label": …, "ref": …}` with what that locale has of them, and `write()` sets them into the link. A translator passes the fingerprint it read back to `write()`, so a source that changed while the translation was in flight leaves the value outdated. Completeness counts and outdated flags read the same service ([Localization](localization.md#completeness-and-outdated-flags)).

## Events

Events are classes in `Setono\SyliusCMSPlugin\Event`, dispatched synchronously on Symfony's event dispatcher.

**Pre-events** are dispatched before the change and can stop it. A listener calls `veto(string $message)`, which stops propagation, and the builder or admin shows the message:

| Event | Properties | Dispatched |
| --- | --- | --- |
| `BeforeEntryPublish` | `entry`, `scheduled` (`bool`) | before a publish, inside its transaction, when scheduling one, and again when a scheduled publish runs. Listeners must not change the entry ([Publishing](publishing.md#publishing-now)) |
| `BeforeEntryDelete` | `entry` | before an entry is deleted |
| `BeforeMediaItemDelete` | `mediaItem` | before a media item is deleted |

**Post-commit events** are dispatched after the database transaction commits:

| Event | Properties | Changes shopper output |
| --- | --- | --- |
| `EntryCreated` | `uuid`, `contentType` | no |
| `EntryPublished` | `uuid`, `contentType`, `oldSlugs` and `newSlugs` (locale to slug), `scheduled` | yes |
| `EntryPublishScheduled` | `uuid`, `at` | no |
| `EntryPublishCancelled` | `uuid` | no |
| `EntryPublishFailed` | `uuid`, `reason` | no |
| `EntrySettingsChanged` | `uuid`, `changed` (a list of `enabled`, `date_window`, `channels`, `audience`, `homepage`, `position`) | yes |
| `EntryDeleted` | `uuid`, `contentType` | yes |
| `PlacementsChanged` | `snippetUuids`, `slots` | yes |
| `MediaItemSaved` | `uuid` | yes |
| `MediaItemReplaced` | `uuid` | yes |
| `MediaItemDeleted` | `uuid` | yes |
| `ImportFinished` | `result` (created, updated and unchanged UUIDs, and unresolved references) | yes |
| `BestsellersComputed` | `channelCodes` | yes |

Every event that changes shopper output implements `StorefrontChangedInterface`, whose `getAffectedUuids(): ?array` lists the UUIDs it touches, so cache invalidation can listen once. `null` means the change can't be narrowed to records, as with `BestsellersComputed`. An empty list means it touched none, such as an import that changed nothing. Nothing in v2.0 listens to them for caching ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)).

## Twig

| Function | Returns | Does |
| --- | --- | --- |
| `cms_field(subject, name)` | safe HTML | Prints a field of a block, a layout or an entry: hydrated, with fallback and bindings resolved, and rich text rendered. In preview mode it adds the marker that lets the builder type it in place. |
| `cms_value(subject, name)` | the hydrated value | Reads a field without printing it, such as an `ItemList` to loop over. |
| `cms_card(entry, display)` | safe HTML | Renders an entry through the card its content type declares for the display, as the Entries block does ([Cards](collections.md#cards)). The card template gets `entry` and `display`, and nothing else of the calling template's. Nothing for an entry whose content type has no card for the display. |
| `cms_pagination(view)` | `?Pagination` | The page of the entry's [paginated Entries block](collections.md#pagination) that shows, when `view` is that block: `entries()` (that page's entries), `links` (a `PageLink` per linked page, with `page`, `url` and `current`, and a gap with a `page` of `null`), `previous` and `next` (`null` on the first and last page), and `pager` (the `Pagerfanta`). Each link leads to the entry's URL with `?page=N`, page 1 without it. `null` for an Entries block that doesn't paginate or is a snippet's. The Entries block's template prints the links through `@SetonoSyliusCMSPlugin/block/_pagination.html.twig`. |
| `cms_block(view)` | safe HTML | Renders one `BlockView`, with its markers in preview mode. |
| `cms_blocks(views)` | safe HTML | Renders `BlockView`s in order. |
| `cms_slot(id, context = {})` | safe HTML | Renders a slot from a template, passing host-page context by key ([Slots](slots.md#rendering-a-slot)). A slot the code doesn't declare throws in dev, and in production prints nothing and is logged at `error` with its identifier ([Rendering](rendering.md#failures)). |
| `cms_image(media, options = {})` | safe HTML | Renders an image media item ([Media](media.md#the-image-helper)). |
| `cms_video(video, options = {})` | safe HTML | Renders a video as the Video block shows it. `video` is a video media item or its UUID, rendered as a `<video>` of its current file with `controls`, `playsinline` and `preload="metadata"`, or a YouTube or Vimeo URL, rendered as the provider's privacy-enhanced embed in a lazy `<iframe>`. Options: `poster` (an image media item or its UUID, over the video media item's own poster, shown as one WebP variant cropped to 16:9 and at most 1280 wide), `title` (the embed's `title`, which names it to screen readers) and `class`. It prints nothing for `null`, a broken reference, a media item that isn't a video or a URL it doesn't know, and in the builder's preview a grey 16:9 placeholder instead ([Blocks](blocks.md#video)). |
| `cms_media_url(media)` | `?string` | The [permanent URL](media.md#permanent-urls) of a document or video media item, or of its UUID, which links to it lead to, such as the Downloads block's. `null` for `null`, a UUID no media item has, or an image, which has no permanent URL. |
| `cms_link(link)` | `?ResolvedLink` | Resolves a `Link` value. `null` means don't render the link. |
| `cms_url(entry)` | `?string` | An entry's path in the current channel and locale, as the `entry` link target builds it (`/{locale}/` for the channel's homepage), or `null` when it has none or isn't visible. Given a taxon, its product listing's path, as the `taxon` link target builds it, or `null` without a translation in the locale. Relative, as Sylius's `path()` gives. |
| `cms_head_tags(entry)` | safe HTML | Prints the meta and link tags of an entry shown at its own URL or as a homepage, for the shop layout's `metatags` block: the meta description, robots, the canonical link and the hreflang links ([URLs and SEO](urls-and-seo.md#head-tags)). A tag added later reaches every layout that calls it. |
| `cms_meta_title(entry)` | `string` | The title text of an entry shown at its own URL or as a homepage, for the shop layout's `title` block: the meta title exactly, or else the title followed by the channel's name ("About us \| Fashion Web Store"), each through the fallback chain ([URLs and SEO](urls-and-seo.md#head-tags)). |
| `cms_preview_mode()` | `?string` | `builder` in the builder's preview, `share` on a share link, otherwise `null`. Themes use it to leave out analytics and other tracking ([Rendering](rendering.md#preview-mode-and-share-links)). |

**Templates** are overridden the Symfony way, under `templates/bundles/SetonoSyliusCMSPlugin/`, or in a Sylius theme. There's no template suggestion chain. The shipped templates are at:

- `@SetonoSyliusCMSPlugin/block/<block type code>.html.twig`
- `@SetonoSyliusCMSPlugin/layout/base.html.twig` and `@SetonoSyliusCMSPlugin/layout/<content type code>/<layout code>.html.twig`
- `@SetonoSyliusCMSPlugin/card/<content type code>/<display>.html.twig`

A card template gets `entry` and `display`.

## Routes

The route files an app imports, and the names and paths of the routes below, are contracts. The app imports three files:

```yaml
# config/routes/setono_sylius_cms.yaml
setono_sylius_cms_admin:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/admin.php'
    prefix: '/%sylius_admin.path_name%'

setono_sylius_cms_media:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/media.php'

setono_sylius_cms_shop:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/shop.php'
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```

The `_locale` requirement is the one `sylius_shop` uses. The admin prefix is Sylius's own admin path (`admin` by default, set by `SYLIUS_ADMIN_ROUTING_PATH_NAME`), so the CMS admin stays under the admin firewall on a shop that renamed it. Admin paths below are relative to it, written `/{admin}`.

| Name | Method | Path | Chapter |
| --- | --- | --- | --- |
| `setono_sylius_cms_shop_entry` | GET | `/{_locale}/{path}`, priority -1000, `path: .+(?<!/)` | [URLs and SEO](urls-and-seo.md#routing) |
| `setono_sylius_cms_media` | GET | `/media/{uuid}/{filename}`, where `{uuid}` matches a UUID | [Media](media.md#permanent-urls) |
| `setono_sylius_cms_admin_overview` | GET | `/{admin}/cms` | [Admin](admin.md#overview) |
| `setono_sylius_cms_admin_entry_index_all` | GET | `/{admin}/cms/entries` (All entries) | [Admin](admin.md#entry-lists) |
| `setono_sylius_cms_admin_entry_index` | GET | `/{admin}/cms/entries/{contentType}`, where `{contentType}` is a content type code | [Admin](admin.md#entry-lists) |
| `setono_sylius_cms_admin_collection_index` | GET | `/{admin}/cms/collections/{contentType}`, a collection with categories, such as the blog: its entries and its categories, where `{contentType}` is its entries' content type code, such as `blog_post` | [Admin](admin.md#entry-lists) |
| `setono_sylius_cms_admin_entry_create` | GET, POST | `/{admin}/cms/entries/{contentType}/new` | [Admin](admin.md#create-dialog) |
| `setono_sylius_cms_admin_entry_show` | GET | `/{admin}/cms/entry/{uuid}` | [Admin](admin.md#entry-overview-page) |
| `setono_sylius_cms_admin_builder` | GET | `/{admin}/cms/entry/{uuid}/builder` | [Builder](builder.md) |
| `setono_sylius_cms_admin_media_index` | GET | `/{admin}/cms/media` | [Admin](admin.md#media-library) |
| `setono_sylius_cms_admin_preset_index` | GET | `/{admin}/cms/presets` | [Admin](admin.md#presets) |
| `setono_sylius_cms_admin_transfer` | GET | `/{admin}/cms/transfer` | [Admin](admin.md#transfer) |

`{uuid}` matches a UUID, and `{contentType}` a content type code. The builder's JSON endpoints, under `/{admin}/cms/api`, are internal: their paths, names and bodies can change in any release ([Builder](builder.md#json-endpoints)). The homepage keeps Sylius's `sylius_shop_homepage` route ([URLs and SEO](urls-and-seo.md#homepage)).

## Configuration

The full tree, with defaults:

```yaml
setono_sylius_cms:
    # Shipped items to switch off. Blocks of a disabled block type already in trees keep rendering.
    disabled:
        block_types: []      # block type codes
        sources: []          # source codes
        presets: []          # code preset codes
        layouts: []          # "<content type code>.<layout code>", such as page.standard
        content_types: []    # a collection as a whole: blog_post with blog_category, faq_item with faq_category

    embed_code: false        # true enables the Embed code block (ADR 0018)
    assets: true             # false stops loading the shipped stylesheet and carousel script

    block_styles:            # styles added to any block type, shipped or not
        # banner:
        #     brand: { label: 'app.cms.block_style.brand', class: 'app-banner--brand' }

    content_types:
        # blog_post:
        #     prefix: 'news'
        #     default_layout: 'article'   # a layout code; overrides #[Layout(default: true)]

    slots:                   # see Slots; the stock slots are prepended here
        # app.store_locator.top:
        #     label: 'app.cms.slot.store_locator_top'
        #     provides: []
        #     cap: null
        #     template_event: { name: 'app.store_locator.top', priority: 0 }

    context_keys:            # product, taxon and entry are prepended here
        # brand: { class: App\Entity\Brand, label: 'app.cms.context.brand' }

    bindable_properties: {}  # see Bindable properties

    fallback_locales:        # the middle of the fallback chain, per locale
        # de_CH: [de_DE]

    builder:
        scripts: []          # field control scripts, as asset paths, such as build/app/cms/controls.js, public paths or absolute http(s) URLs, each resolved through asset(), loaded as ES modules in this order

    presets:
        paths: []            # directories of code preset files; bundles prepend theirs, and the plugin its shipped presets' src/Resources/presets

    transfer:
        storage: 'setono_sylius_cms.transfer_storage'  # a Flysystem storage service uploaded export zips wait in for the worker; the default writes to %kernel.project_dir%/private/cms, outside the web root, so a worker on another server needs a storage both share
        max_file_size: 100M      # the largest media file an import takes; media.max_upload_size is the limit when it's smaller
        max_unpacked_size: 1G    # the most an export zip unpacks to, checked from its directory before anything is extracted and again while extracting

    media:
        storage: 'setono_sylius_cms.storage'     # a Flysystem storage service; the default writes to %kernel.project_dir%/public/media/cms, served at /media/cms; another needs MediaUrlGeneratorInterface aliased (Images)
        liip:
            loader: 'setono_sylius_cms'
            resolver: 'setono_sylius_cms'
        types:
            image: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml']
            video: ['video/mp4', 'video/webm']
            document: ['application/pdf']
        max_upload_size: null  # null means PHP's effective limit; a value above it is lowered to it. Imports check against the smaller of this value and transfer.max_file_size, which PHP's limit doesn't lower
        widths: [320, 640, 960, 1280, 1600, 1920, 2560]
        grace_period: 'P1D'  # ISO 8601 duration before a replaced file is deleted

    revisions:
        keep: 50             # per entry; older ones are pruned by setono:cms:revisions:prune

    bestsellers:
        window: 'P30D'       # ISO 8601 duration the default calculator counts completed orders over

    resources:               # the shipped Sylius resources, registered as setono_sylius_cms.<name>
        page:
            options: ~
            classes:
                model: Setono\SyliusCMSPlugin\Model\Page
                interface: Setono\SyliusCMSPlugin\Model\PageInterface
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                repository: ~        # Sylius's default repository
                factory: Sylius\Resource\Factory\TranslatableFactory
            translation:
                options: ~
                classes:
                    model: Setono\SyliusCMSPlugin\Model\PageTranslation
                    interface: Setono\SyliusCMSPlugin\Model\PageTranslationInterface
                    controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                    repository: ~
                    factory: Sylius\Resource\Factory\Factory
        # The other shipped content types are added here as they're built, in the same shape
```

A project caps a stock slot by setting `slots.<id>.cap`, moves a shipped content type's URLs with `content_types.<code>.prefix`, and changes its default layout with `content_types.<code>.default_layout`. A disabled layout is hidden from editors, and its entries render with the default layout, as for a [removed layout](layouts-and-presets.md#a-removed-layout). The default layout can't be disabled.

## Console commands

| Command | Does |
| --- | --- |
| `setono:cms:check-routes` | Lists every entry URL that another route now matches, with its channel, locale, URL and route. Exits non-zero when it finds any, for CI and deploys. It also counts the published entries' [problems](admin.md#problems) again, so the lists and the Content badge show a URL a route added since takes. |
| `setono:cms:export <dir> [--entry=UUID]... [--content-type=CODE]... [--media-folder=UUID]... [--presets] [--all] [--zip]` | Exports ([Transfer](transfer.md#commands)). |
| `setono:cms:import <path> [--dry-run] [--publish] [--overwrite]` | Imports a folder or zip. A dry run exits with the code the real run would ([Transfer](transfer.md#commands)). |
| `setono:cms:preset:export <preset> <dir>` | Writes an editor preset, by UUID or name, to a code preset file. |
| `setono:cms:preset:validate [<path>]...` | Validates code preset files, by default in every registered directory. Exits non-zero on any error. |
| `setono:cms:bestsellers:compute [--channel=CODE]` | Recomputes bestseller scores, for every channel by default. Run it from cron. |
| `setono:cms:upgrade [--dry-run]` | Runs the upgraders over all stored data and writes it back: rows, drafts, revisions and editor presets. |
| `setono:cms:lint` | Reports stored values that don't fit their field type, blocks of unknown types and broken references. Exits non-zero on any. |
| `setono:cms:revisions:prune` | Deletes revisions beyond `revisions.keep` per entry. |

## Fixture

The Sylius fixture `setono_cms_import` imports an export folder, or zip, and publishes everything it imported, for demo and test data. An import that refuses, such as for a conflict or a URL clash, fails the load with its reasons:

```yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                setono_cms_import:
                    options:
                        path: '%kernel.project_dir%/fixtures/cms'
```
