# Extending

The developer-facing API: attributes, field types, interfaces, events, Twig functions, routes, config and console commands. Everything in this chapter is a public contract.

- **Terms:** the whole [glossary](../../GLOSSARY.md).
- **Decisions:** [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (extension points are classes whose typed properties are their fields), [ADR 0022](../adr/0022-sources-return-a-pagerfanta-adapter.md) (sources return a Pagerfanta adapter), [ADR 0004](../adr/0004-slots-have-platform-neutral-identifiers.md) (slot identifiers), [ADR 0006](../adr/0006-bindings-use-declared-properties.md) (declared bindable properties), [ADR 0014](../adr/0014-images-are-processed-by-liip-behind-an-image-provider.md) (image provider), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (rendering on every request).

## The model

Everything a developer adds is a PHP class registered by attribute: block types, layouts, content types, sources, condition kinds, bindable properties, link targets, context resolvers and upgraders. Where one of these has settings that editors fill in, each setting is a typed property with a [field attribute](#field-types). Symfony Validator constraints sit on the same properties.

- **Discovery.** Classes with these attributes are registered through Symfony autoconfiguration, so in an app they only need to be under a resource the app's `services.yaml` loads. A bundle registers them as services. Block types and layouts are registered as metadata, not as shared services, because they're hydrated per use.
- **Services.** Sources, condition kinds, bindable properties, link targets, context resolvers, upgraders, field type handlers and block renderers are services and can take constructor dependencies.
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
| `template` | `string` | required | The Twig template the default renderer renders. |
| `children` | `list<Allow>` | `[]` | Which block types the block may hold. Empty means none. |
| `parents` | `list<class-string>\|null` | `null` | The only block types this one may sit inside. `null` means anywhere a parent allows it. |
| `needs` | `list<string>` | `[]` | The context keys the block type needs ([Slots](slots.md#context-keys)). |
| `styles` | `list<Style>` | `[]` | The block styles it offers. Projects add more in config. |
| `thumbnail` | `string\|null` | `null` | A public path to the picker's preview image. |
| `renderer` | `class-string<BlockRendererInterface>\|null` | `null` | A renderer other than the default ([Block renderers](#block-renderers)). |

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

### Block renderers

```php
namespace Setono\SyliusCMSPlugin\Rendering;

interface BlockRendererInterface
{
    public function render(BlockView $view, RenderContext $context): string;
}
```

The default renderer renders the block type's `template`. `#[BlockType(renderer: …)]` names another one. This is the seam for a future sandboxed code block, whose renderer would render a template stored in one of its fields through Twig's sandbox.

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

Layout fields are the class's typed properties, stored in the tree's `layout.fields` ([Layouts and presets](layouts-and-presets.md)).

### Layout templates

A layout template extends `@SetonoSyliusCMSPlugin/layout/base.html.twig`. That template extends `@SyliusShop/layout.html.twig`, fills its `title` and `metatags` blocks ([URLs and SEO](urls-and-seo.md#head-tags)), and renders the block `cms_content` inside `content`. A layout template gets:

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
- **Extending a shipped content type.** Subclass its model, and its translation model if needed, and override `classes.model` and `translation.classes.model` of its resource. The subclass keeps the shipped `#[ContentType]`: the plugin reads the attribute from the nearest class in the hierarchy that has one.
- **Disabling a shipped content type** with `disabled.content_types` hides it from the admin, the builder, sources and routing. Its columns stay mapped.

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
- **Dynamic choices.** A `Choice` field whose choices come from the store, such as the association types of the Associations source, names a service implementing `Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface`, whose `getChoices(string $localeCode): array` returns value to label. It's used instead of `choices`.
- **`ItemList`** (`Setono\SyliusCMSPlugin\Value\ItemList`) is iterable and countable over the items that show, resolved lazily on first read. `adapter()` returns the Pagerfanta adapter, and `pager(int $page)` returns a `Pagerfanta` for the paginated Entries block.
- **`Entries(indexed: true)`** writes the field's UUIDs to the (entry, field, UUID) reference table on publish ([Collections](collections.md#categories)).
- **No colour, spacing or icon fields** ([ADR 0017](../adr/0017-shipped-blocks-are-theme-agnostic.md)), and no repeater ([ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md)).

The stored value of each type is in [Content model](content-model.md#values-per-field-type).

### Adding a field type

A field attribute class extends `Field` and carries `#[FieldType]`, which declares what the plugin needs before it stores or shows a value:

```php
use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_opening_hours', translatable: false, bindable: [], widget: 'app-opening-hours')]
final class OpeningHours extends Field
{
}
```

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `code` | `string` | required | The field type's code. Unique, and never changes once content uses it. |
| `translatable` | `bool` | `false` | Its default translatability, used when a field leaves `translatable:` at `null`. It decides whether the plugin stores one value or a locale map of values. A field type can still keep locale maps inside its one value: Link declares `false` and keeps its label per locale, and its handler reads the field's own `translatable` to pick the [link's shape](content-model.md#links). |
| `bindable` | `list<BindingType>` | `[]` | The binding types a field of this type accepts. Empty means it can't be bound. |
| `widget` | `string\|null` | `null` | The custom element's tag name ([Widgets](#widgets)). Required for a project's own field type. The shipped field types have none. |

The shipped field types carry it too, with the codes, defaults and binding types in the table above.

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
| `x-cms-widget` | The custom element's tag name, for a project's own field type. | the plugin, from `#[FieldType(widget:)]` |
| `x-cms-label`, `x-cms-help` | Translated strings. | the plugin, from the field's `label` and `help` |
| `x-cms-required` | `true` or `false`. | the plugin, from the field's `required` |
| `x-cms-translatable` | `true` or `false`. | the plugin, from the field's `translatable`, or the field type's default |
| `x-cms-bindable` | The binding types the field accepts (`text`, `number`, `url`), or absent. | the plugin, from `#[FieldType(bindable:)]` |
| `x-cms-options` | The field type's own options, as JSON. | the handler |

Everything else in the schema, the JSON Schema for one value, is the handler's.

### Widgets

A project's own field type comes with a widget: a custom element, loaded from a script listed under `setono_sylius_cms.builder.scripts`. A block type built only from shipped field types needs no JS.

- The builder sets four properties on the element: `.value` (the stored value, for the current locale when the field is translatable), `.field` (its schema), `.locale` (the locale being edited) and `.builder`.
- `.builder` has `search(kind, query)`, which resolves to `[{ref, name, thumbnail}]` for `product`, `taxon`, `entry`, `media` or a link target's code, `pickMedia({kinds, multiple})`, which resolves to UUIDs or `null`, and `trans(key, params)`.
- The widget dispatches a bubbling `cms-change` `CustomEvent` with `detail.value` whenever its value changes.

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

A source that needs `entry` and fits only some content types also implements `EntryContextAwareInterface::supportsEntryContentType(string $code): bool`. The builder offers it only for those types. "In this category" and "Related" do this.

An entry source whose `of` is `EntryInterface::class` can say which content type it lists, so the Entries block can offer that type's [card displays](collections.md#the-entries-block):

```php
namespace Setono\SyliusCMSPlugin\Source;

interface ListedContentTypeAwareInterface
{
    /** The code of the content type it lists with these parameters, in an entry of $hostContentType (null outside an entry). Null when it can't tell. */
    public function getListedContentType(object $params, ?string $hostContentType): ?string;
}
```

Without it, or when it returns `null`, the listed type is the one `of` names. When that's `EntryInterface::class` too, the block offers the displays that every content type with cards has. All three shipped entry sources implement it.

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

`#[AsConditionKind(code, label, needs = [], reads = null, params = null)]`. `reads` is a list of `Setono\SyliusCMSPlugin\Condition\Reads` cases: `HostPage`, `Channel`, `Locale`, `Customer` and `Time`. `null`, the default, means unknown, and a later cache treats a kind that hasn't declared what it reads as uncacheable ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)). `[]` claims it reads nothing. A kind that reads `Time` implements `TimeDependentConditionKindInterface`.

`matches()` answers "is one of". The plugin provides "is not one of" by negating it, so kinds never implement it. Kinds work in visibility conditions on blocks and in page scopes, never on entries ([ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md)). The shipped kinds are in [Targeting](targeting.md#condition-kinds).

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

| Code | Refers to | Resolves to |
| --- | --- | --- |
| `entry` | an entry with its own URL, by UUID | its URL in the current channel and locale, or `null` when it isn't visible |
| `product` | a product, by code | its shop URL, or `null` when it isn't visible ([Sources](sources.md#visible-queries)) |
| `taxon` | a taxon, by code | its shop URL, or `null` when it isn't enabled |
| `media` | a document or video media item, by UUID | its permanent URL ([Media](media.md#permanent-urls)) |
| `url` | a URL, per locale (`translatableRef: true`), including `mailto:` and `tel:` | the URL as typed |

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

## Bestsellers

```php
namespace Setono\SyliusCMSPlugin\Bestsellers;

interface BestsellersCalculatorInterface
{
    /** @return iterable<string, int|float> product code => score; higher sells better */
    public function calculate(ChannelInterface $channel): iterable;
}
```

`setono:cms:bestsellers:compute` calls it per channel and stores the scores. The default counts paid orders over `bestsellers.window`. A project with analytics data aliases the interface to its own calculator ([Sources](sources.md#bestsellers)).

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

Each value has a `path` (`entry.<field>`, `layout.<field>` or `block.<key>.<field>`), a `state` (`Untranslated`, `Outdated` or `UpToDate`), the source locale, the source value, the source's current fingerprint and the current value. A translator passes the fingerprint it read back to `write()`, so a source that changed while the translation was in flight leaves the value outdated. Completeness counts and outdated flags read the same service ([Localization](localization.md#completeness-and-outdated-flags)).

## Events

Events are classes in `Setono\SyliusCMSPlugin\Event`, dispatched synchronously on Symfony's event dispatcher.

**Pre-events** are dispatched before the change and can stop it. A listener calls `veto(string $message)`, which stops propagation, and the builder or admin shows the message:

| Event | Properties | Dispatched |
| --- | --- | --- |
| `BeforeEntryPublish` | `entry`, `scheduled` (`bool`) | before a publish, when scheduling one, and again when a scheduled publish runs |
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
| `cms_block(view)` | safe HTML | Renders one `BlockView`, with its markers in preview mode. |
| `cms_blocks(views)` | safe HTML | Renders `BlockView`s in order. |
| `cms_slot(id, context = {})` | safe HTML | Renders a slot from a template, passing host-page context by key ([Slots](slots.md#rendering-a-slot)). |
| `cms_image(media, options = {})` | safe HTML | Renders an image media item ([Media](media.md#the-image-helper)). |
| `cms_link(link)` | `?ResolvedLink` | Resolves a `Link` value. `null` means don't render the link. |
| `cms_url(entry)` | `?string` | An entry's URL in the current channel and locale, or `null` when it has none or isn't visible. |
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
        content_types: []    # blog_post, blog_category, faq_item, faq_category

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
        scripts: []          # widget scripts, as public paths or absolute URLs, loaded as ES modules

    presets:
        paths: []            # directories of code preset files; bundles prepend theirs

    media:
        storage: 'setono_sylius_cms.storage'     # a Flysystem storage service; the default writes to %kernel.project_dir%/public/media/cms
        liip:
            loader: 'setono_sylius_cms'
            resolver: 'setono_sylius_cms'
        types:
            image: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml']
            video: ['video/mp4', 'video/webm']
            document: ['application/pdf']
        max_upload_size: null  # null means PHP's effective limit; a value above it is lowered to it
        widths: [320, 640, 960, 1280, 1600, 1920, 2560]
        grace_period: 'P1D'  # ISO 8601 duration before a replaced file is deleted

    revisions:
        keep: 50             # per entry; older ones are pruned by setono:cms:revisions:prune

    bestsellers:
        window: 'P30D'       # ISO 8601 duration the default calculator counts paid orders over
```

A project caps a stock slot by setting `slots.<id>.cap`, moves a shipped content type's URLs with `content_types.<code>.prefix`, and changes its default layout with `content_types.<code>.default_layout`. A disabled layout is hidden from editors, and its entries render with the default layout, as for a [removed layout](layouts-and-presets.md#a-removed-layout). The default layout can't be disabled.

## Console commands

| Command | Does |
| --- | --- |
| `setono:cms:check-routes` | Lists every entry URL that another route now matches, with its channel, locale, URL and route. Exits non-zero when it finds any, for CI and deploys. |
| `setono:cms:export <dir> [--entry=UUID]... [--content-type=CODE]... [--media-folder=UUID]... [--presets] [--all] [--zip]` | Exports ([Transfer](transfer.md#commands)). |
| `setono:cms:import <path> [--dry-run] [--publish] [--overwrite]` | Imports a folder or zip. A dry run exits with the code the real run would ([Transfer](transfer.md#commands)). |
| `setono:cms:preset:export <preset> <dir>` | Writes an editor preset, by UUID or name, to a code preset file. |
| `setono:cms:preset:validate [<path>]...` | Validates code preset files, by default in every registered directory. Exits non-zero on any error. |
| `setono:cms:bestsellers:compute [--channel=CODE]` | Recomputes bestseller scores, for every channel by default. Run it from cron. |
| `setono:cms:upgrade [--dry-run]` | Runs the upgraders over all stored data and writes it back: rows, drafts, revisions and editor presets. |
| `setono:cms:lint` | Reports stored values that don't fit their field type, blocks of unknown types and broken references. Exits non-zero on any. |
| `setono:cms:revisions:prune` | Deletes revisions beyond `revisions.keep` per entry. |

## Fixture

The Sylius fixture `setono_cms_import` imports an export folder and publishes everything in it, for demo and test data:

```yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                setono_cms_import:
                    options:
                        path: '%kernel.project_dir%/fixtures/cms'
```
