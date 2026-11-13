# Extension points are classes whose typed properties are their fields

Everything a developer adds to the CMS is a PHP class registered by attribute: block types, layouts, sources, condition kinds, bindable properties, link targets, context resolvers and upgraders. Where one of these has settings that editors fill in, such as a block's heading, a layout's "Hide the title" or the taxon a "Products in taxon" source reads, each setting is a typed property of the class carrying a field attribute, such as `#[Text(label: 'Heading', translatable: true)]`. The class attribute holds the rest, such as a block type's code, version, template and allowed children. Symfony Validator constraints sit on the same properties, and Twig gets the hydrated object.

Content types follow the same model, because they are Doctrine entity subclasses (ADR 0001) and their fields are already mapped properties. A content type is a Sylius resource, registered with `#[AsResource]` or `sylius_resource.resources`, and our `#[ContentType]` attribute adds its CMS metadata. A project extends a shipped content type the way it extends any Sylius model, by overriding `classes.model` with a subclass. The plugin prefixes each content type's columns with its code, so they can't collide in the shared table.

A field type is a pair, like a Symfony validation constraint and its validator: an attribute class that carries its typed options, and a handler service that exports its JSON schema to the builder, hydrates stored values for Twig, lists the references it holds for transfer and the media usage index, and validates. Repeated items that have fields of their own, such as slides or FAQ rows, are child blocks. There is no repeater field.

We chose this because it is how modern Symfony declares data: Doctrine, Validator, Serializer and API Platform all put attributes on typed properties. With one model, content types and block types read the same way, and a developer who has written one block type can write a layout, a source or a condition kind. Making content types Sylius resources gives them the factories, repositories and model overrides that Sylius developers already know.

## Considered Options

- **A builder method**, such as `configureFields(FieldsBuilder $f)`, like Symfony form types' `buildForm()`. Fields could be built dynamically, but nothing would be typed, and content types would still declare their fields as entity properties, so there'd be two models.
- **A field type as one service with an untyped options array**, such as `#[Field(type: ProductsFieldType::class, options: [...])]`. That's one class instead of two, but its options can't be checked or autocompleted.
- **A repeater field**, as in ACF or Payload. Light repeated data would need no block per item, but it's a second way to nest, which the builder, translation, upgraders and transfer would all have to handle.
- **Content types registered by `#[ContentType]` alone**, with the plugin registering their Sylius resources itself. That's one attribute, but resource registration would happen behind the developer's back.

## Consequences

- A version bump and an upgrader are needed only when new code can't read old stored data as it is: renaming a field, or changing its type or its translatability. Adding a field needs none, because it takes its default. Removing one needs none either, because its value is ignored and dropped on the next save. A stored value that doesn't fit its field type counts as missing, and a lint command reports it. This refines ADR 0001, ADR 0009 and ADR 0011, and applies to block types, layouts and content types alike.
- Upgraders work on raw arrays and run whenever stored data is read: rows, drafts, revisions, presets and export files. A console command rewrites stored data in bulk.
- A content type's code can never change, because it names both its discriminator value and its column prefix.
- `#[AsResource]` has no translation option, so a content type with a translation subclass registers through `sylius_resource.resources`.
- Hydrated values that refer to other records, such as a product list filled by a source, are resolved lazily when a template reads them.
- A source's `resolve` returns a Pagerfanta adapter that the caller pages, so the block holding the list, not the source, decides how many items show (ADR 0022).
- Slots and context keys are pure data, so they're declared in bundle config, not as classes. Context resolvers are classes registered per context key.
