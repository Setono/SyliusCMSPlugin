# Slots

Slots in shop pages and layouts, host-page context, placements, page scopes and bindings.

- **Terms:** [shop pages](../../GLOSSARY.md#shop-pages) (slot, placement, page scope, host-page context, binding) and [entries](../../GLOSSARY.md#entries) (snippet).
- **Decisions:** [ADR 0004](../adr/0004-slots-have-platform-neutral-identifiers.md) (platform-neutral slot identifiers), [ADR 0005](../adr/0005-placements-take-effect-on-save.md) (placements take effect on save), [ADR 0006](../adr/0006-bindings-use-declared-properties.md) (declared bindable properties), [ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md) (audience, capped slots), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (customer bindings ship).

## Slots

A [slot](../../GLOSSARY.md#shop-pages) is declared in code with its own identifier, such as `product.after_price`. It's never a Sylius template event name. Editors fill slots but never create them.

There are three kinds of declaration, and all of them are one concept:

- **Stock slots.** The plugin ships about 20 slots for positions in the stock Sylius shop ([Stock slots](#stock-slots)).
- **Project slots.** Developers declare their own, tied to any template event or rendered from their own templates with `cms_slot()`.
- **Layout slots.** A layout's template renders slots of its own with `cms_slot()`. They're declared in config like any other slot.

Slots are declared under `setono_sylius_cms.slots` ([Extending](extending.md#configuration)):

```yaml
setono_sylius_cms:
    slots:
        app.store_locator.top:
            label: 'app.cms.slot.store_locator_top'
            provides: [store_location]
            cap: ~
            template_event: { name: 'app.store_locator.top', priority: 0 }
```

| Key | Meaning |
| --- | --- |
| `label` | A translation key, shown in the placement editor and the profiler panel. |
| `provides` | The context keys the slot provides ([Context keys](#context-keys)). |
| `cap` | `null` for no cap, or the number of placements that show at most ([Capped slots](#capped-slots)). |
| `template_event` | Ties the slot to a Sylius 1.14 template event, with the block's priority. Leave it out for a slot rendered with `cms_slot()`. The Sylius 2.x port adds a `twig_hook` key beside it. |

The identifier is what placements store. On Sylius 1.14, the plugin adds one block to each tied template event, which renders the slot. Only that tie changes in the Sylius 2 port.

**Slots don't limit block types.** Any snippet whose context fits can be placed in any slot. The builder's preview shows when something doesn't fit the space.

**An empty slot prints nothing**, not even a wrapper ([Rendering](rendering.md)).

## Stock slots

The plugin prepends these to `setono_sylius_cms.slots`. None is capped, and a project caps one by setting its `cap`.

| Identifier | Where | Sylius 1.14 template event (priority) | Provides |
| --- | --- | --- | --- |
| `layout.top` | Every page: the top of the page | `sylius.shop.layout.before_body` (0) | |
| `layout.before_content` | Every page: above the main content | `sylius.shop.layout.before_content` (0) | |
| `layout.after_content` | Every page: below the main content | `sylius.shop.layout.after_content` (0) | |
| `layout.footer` | Every page: the footer | `sylius.shop.layout.footer` (-10) | |
| `homepage` | Sylius's homepage, above its own blocks | `sylius.shop.homepage` (100) | |
| `product.top` | Product page: above the main content | `sylius.shop.layout.before_content` (-10) | `product`, resolved |
| `product.sidebar` | Product page: the bottom of the column beside the images | `sylius.shop.product.show.right_sidebar` (1) | `product` |
| `product.after_price` | Product page: under the price | `sylius.shop.product.show.right_sidebar` (28) | `product` |
| `product.before_tabs` | Product page: above the tabs | `sylius.shop.product.show.content` (22) | `product` |
| `product.details_tab` | Product page: inside the details tab, after the description | `sylius.shop.product.show.tab_details` (5) | `product` |
| `product.after_associations` | Product page: below the associations | `sylius.shop.product.show.content` (5) | `product` |
| `taxon.header` | Taxon page: the header | `sylius.shop.taxon.header` (1) | `taxon` |
| `taxon.before_grid` | Taxon page: above the product grid | `sylius.shop.product.index.before_list` (0) | `taxon`, resolved |
| `taxon.after_grid` | Taxon page: below the product grid | `sylius.shop.product.index.before_pagination` (-10) | `taxon`, resolved |
| `taxon.sidebar` | Taxon page: the sidebar, below the taxon menu | `sylius.shop.product.index.after_vertical_menu` (0) | `taxon` |
| `cart.after_summary` | Cart: below the summary, above the suggestions | `sylius.shop.cart.suggestions` (100) | |
| `checkout.sidebar` | Checkout: the sidebar on every step | `sylius.shop.checkout.sidebar` (0) | |
| `order.thank_you` | Thank-you page: after the message | `sylius.shop.order.thank_you.after_message` (0) | |
| `account.dashboard` | Account dashboard: after the customer's information | `sylius.shop.account.dashboard.after_information` (0) | |

"Resolved" means the template event doesn't pass that key, so the slot gets it from a context resolver ([Context resolvers](#context-resolvers)). `product.top` sits in a layout event that fires on every page, so it renders only where the `product` resolver finds a product.

On a channel with a CMS [homepage](../../GLOSSARY.md#entries), Sylius's homepage template doesn't render, so placements in `homepage` don't show there ([URLs and SEO](urls-and-seo.md#homepage)). The admin flags them ([Admin](admin.md#problems)).

## Context keys

[Host-page context](../../GLOSSARY.md#shop-pages) is a set of named, typed keys. A slot declares the keys it provides. Block types, sources, condition kinds and bindable properties declare the keys they need, and the builder matches the two.

Context keys are declared under `setono_sylius_cms.context_keys` ([Extending](extending.md#context-keys-and-resolvers)). The plugin declares three:

| Key | Class | Provided by |
| --- | --- | --- |
| `product` | `Sylius\Component\Core\Model\ProductInterface` | product page slots |
| `taxon` | `Sylius\Component\Core\Model\TaxonInterface` | taxon page slots |
| `entry` | `Setono\SyliusCMSPlugin\Model\EntryInterface` | an entry the CMS renders, to its own block tree and to its layout's slots |

The channel, locale, currency and customer are available everywhere, so they're never context keys. Conditions and bindings reach them directly ([Targeting](targeting.md#condition-kinds), [Bindings](#bindings)).

**Context flows down the tree.** A block, its children, the sources in their fields and the bindings in their values all see the same host-page context. A Snippet block passes the context of the tree it sits in on to the snippet's tree.

## Context resolvers

A slot whose template event doesn't pass a key it provides runs that key's context resolver ([Extending](extending.md#context-keys-and-resolvers)). When the resolver returns `null`, the slot renders nothing on that page.

The plugin ships two resolvers:

- **`product`** finds the product from the product page's route and slug.
- **`taxon`** finds the taxon from the taxon page's URL. The grid events on the taxon page don't carry `taxon`, so `taxon.before_grid` and `taxon.after_grid` use it.

A project registers a resolver for its own context key, or replaces a shipped one, with `#[AsContextResolver]`.

## Snippets and their context

A [snippet](../../GLOSSARY.md#entries) declares the host-page context it needs, as a list of context keys, such as `[product]` for "Product pages" or `[]` for "Any page". It's a drafted field of the snippet, set in the builder's entry settings ([Admin](admin.md#entry-overview-page)).

- It can be **placed** in any slot that provides at least those keys.
- It can be **referenced** from a block tree, through a Snippet block, only where the tree provides those keys.
- The builder offers only the block types, sources, condition kinds and bindings whose needs it covers.

**The declared context can't change while something depends on it.** A block, a binding, a source, a condition, a placement or a reference that needs a key blocks removing it, and adding a key is blocked while a placement or reference sits where that key isn't provided. The admin lists what's in the way.

## Placements

A [placement](../../GLOSSARY.md#shop-pages) shows one snippet in one slot. It has a UUID, the slot's identifier, a [page scope](#page-scopes) and a position in the slot. A snippet can have many placements, such as one in `layout.footer` with an empty scope and one in `product.sidebar` scoped to the Shoes taxon.

- **Placements take effect on save** ([ADR 0005](../adr/0005-placements-take-effect-on-save.md)). They don't go through drafts, and adding, editing, removing or reordering them fires `PlacementsChanged` ([Extending](extending.md#events)) and bumps the snippet's `changedAt`.
- **The snippet decides when its content shows.** A placement shows its snippet only while the snippet is visible ([Content model](content-model.md#when-an-entry-shows)): a placement of an unpublished, disabled or expired snippet shows nothing.
- **Editors manage placements on the snippet's overview page**, not in the builder, where they're read-only. There's no Slots screen ([Admin](admin.md#entry-overview-page)).
- **Position.** Each slot's placements have one order across all snippets, which editors set. A slot shows its matching placements in that order.

**Per-product content at scale**, like Amazon's A+ content, gets no mechanism of its own. A placement scoped to one product covers the occasional one-off. At scale, developers model a collection whose entries reference a product, plus a block type that shows the entry for the current product.

## Page scopes

A [page scope](../../GLOSSARY.md#shop-pages) is a list of conditions on host-page context, in the [stored condition format](content-model.md#visibility-conditions). It uses the same condition kinds as visibility conditions, limited to those that read `HostPage` ([Targeting](targeting.md#condition-kinds)):

- product is one of (`product`)
- product is in taxon (`product_in_taxon`), optionally including child taxons
- taxon is one of (`taxon`)
- entry is one of (`entry`)
- entry is in category (`entry_in_category`)
- kinds developers add that read `HostPage`

Rules:

- Conditions combine with AND, and the values within one condition with OR. Every kind offers "is one of" and "is not one of".
- An empty scope means every page the slot appears on.
- A condition kind can sit in a placement's scope only when the slot provides the keys it needs.
- Who sees it (channel, locale, customer group, dates) stays on the snippet: its channels, translations, audience and date window. Visibility conditions on the snippet's blocks handle the rest.

## Capped slots

A slot can declare a cap in code, such as 1 for a hero or a top bar, and a project can cap a stock slot in config. In a capped slot, the first `cap` matching placements in editor order win. The rules, including the catch-all fallback, are in [Targeting](targeting.md#capped-slots).

The order of a capped slot's placements is set in a dialog on the snippet's overview page, which lists all the slot's placements ([Admin](admin.md#entry-overview-page)).

## Bindings

A [binding](../../GLOSSARY.md#shop-pages) shows a property of the host-page context, the channel or the customer instead of a fixed value ([ADR 0006](../adr/0006-bindings-use-declared-properties.md)). It's either a field value or an inline token in rich text, stored as described in [Content model](content-model.md#field-values).

- **Only declared properties can be bound.** Each is declared in code for a context key, `channel` or `customer`, with a label and a type ([Extending](extending.md#bindable-properties)). Editors can't enter a property path.
- **The picker lists only properties that fit** the field's binding types and the context the entry provides or the snippet declares. Channel and customer properties fit everywhere.
- **Binding types** are `text`, `number` and `url`. Text and Textarea fields accept `text` and `number`, Number fields accept `number`, and Link fields accept `url`. Rich text tokens accept `text` and `number`. There are no image bindings in v2.0.
- **Fallback.** A binding can carry a fallback for when its value is empty, such as "Hi there" for a guest's first name. The fallback follows the field's translatability. Without one, the field renders empty. This is different from the [fallback chain](localization.md#fallback-chain), which is about locales.
- **Bound values can't be typed in place** in the builder's preview ([Builder](builder.md)).

### Shipped bindable properties

| Binding | Type | Value |
| --- | --- | --- |
| `product.name` | text | the product's name in the current locale |
| `product.short_description` | text | its short description |
| `product.description` | text | its description |
| `product.price` | text | the price of its first enabled variant in the channel, formatted in the current currency |
| `product.original_price` | text | that variant's original price, formatted the same way, or empty when it has none |
| `product.code` | text | its code |
| `product.url` | url | its shop URL |
| `product.main_taxon_name` | text | its main taxon's name |
| `product.attribute` | text | the value of one product attribute. Its parameter `attribute` is the attribute's code, which the editor picks from the store's attributes, so attributes need no declaration each. |
| `taxon.name` | text | the taxon's name |
| `taxon.description` | text | its description |
| `taxon.url` | url | its shop URL |
| `entry.title` | text | the entry's title |
| `entry.url` | url | its URL, or empty when it has none |
| `channel.name` | text | the channel's name |
| `channel.contact_email` | text | its contact email |
| `channel.contact_phone` | text | its contact phone number |
| `customer.first_name` | text | the signed-in customer's first name, or empty for a guest |
| `customer.last_name` | text | their last name |
| `customer.full_name` | text | their full name |

Customer bindings ship in v2.0, because v2.0 caches nothing it renders ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)).

## Mismatches

- **The admin prevents them.** A snippet can't be placed or referenced where its context isn't provided, and its declared context can't change while something depends on it ([Snippets and their context](#snippets-and-their-context)).
- **What developers break in code is kept but not rendered.** A placement in a slot that was removed from config, a page-scope condition of a removed kind, or a snippet needing a context key that was removed stays stored, shows nothing, and is flagged in the admin like a broken reference ([Admin](admin.md#problems)).

## Rendering a slot

A slot tied to a template event renders itself there. A slot without a `template_event` is rendered from a template, passing the host-page context it provides by key:

```twig
{{ cms_slot('app.store_locator.top', { store_location: location }) }}
```

A layout's slots receive the entry being shown:

```twig
{{ cms_slot('page.landing.after_hero', { entry: entry }) }}
```

A key the call doesn't pass is resolved by its context resolver, if there is one ([Context resolvers](#context-resolvers)). How a slot picks and renders its placements is in [Rendering](rendering.md).

## Previewing a snippet

The builder previews a snippet on the real shop page that hosts one of its placements, with the draft in its slot and the slot outlined and labelled.

- The top bar shows the snippet's context, such as "Product page", with a placement picker and a sample-object picker.
- The sample object defaults to one that matches the placement's page scope, and the picker marks the ones that match. A warning shows when the chosen sample object is outside the scope.
- Bindings show the sample object's real values.
- A snippet with no placements previews on a plain page with a sample object.

The details are in [Builder](builder.md).
