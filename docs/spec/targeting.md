# Targeting

Who sees what: visibility conditions on blocks, an entry's audience, capped slots, and how the preview forces a shopper and a date.

- **Terms:** [visibility](../../GLOSSARY.md#visibility) (visibility condition, audience), [shop pages](../../GLOSSARY.md#shop-pages) (slot, placement, page scope) and [publishing](../../GLOSSARY.md#publishing) (date window, share link).
- **Decisions:** [ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md) (entries have an audience, not conditions), [ADR 0002](../adr/0002-references-by-code-and-uuid.md) (dangling codes), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (nothing is cached in v2.0).

## Where targeting sits

| On | What | Filtered |
| --- | --- | --- |
| A block | [Visibility conditions](#visibility-conditions), any kind | in PHP, before the block's template renders |
| An entry | Channels, translations, `enabled`, the date window and the [audience](#an-entrys-audience) | in SQL, so listings and sources page correctly |
| A placement | A [page scope](slots.md#page-scopes): conditions on host-page context | in PHP, when the slot renders |

Entries never carry visibility conditions. Host-page conditions for a snippet belong in its placement's page scope, and everything else goes on its blocks.

## Visibility conditions

A [visibility condition](../../GLOSSARY.md#visibility) on a block hides it unless the shopper or the page matches. A hidden block hides its children.

- **Stored** in the block's `conditions`, in the [stored condition format](content-model.md#visibility-conditions).
- **Combining.** A block's conditions combine with AND, and the values within one condition with OR.
- **"Is one of" and "is not one of".** Every kind offers both, as the condition's `negate` flag. For a date window, that's inside or outside it, such as hiding a block during one weekend.
- **Any block** can carry conditions, such as a single carousel slide.
- **The builder** strikes through blocks that the preview's channel and locale hide, with an eye-slash icon in the tree, and drops them from the preview ([Builder](builder.md)).

## Condition kinds

These kinds ship. Each declares the context keys it needs and what it reads ([Extending](extending.md#condition-kinds)).

| Code | Label | Needs | Reads | Parameters |
| --- | --- | --- | --- | --- |
| `channel` | Channel | | `Channel` | `channels`: channel codes |
| `locale` | Locale | | `Locale` | `locales`: locale codes |
| `date_window` | Date window | | `Time` | `from` and `until`: Date and time values, each optional. `from` is inclusive and `until` exclusive, so a window that ends at `2026-12-01T00:00:00Z` shows until the last second of 30 November. |
| `customer_group` | Customer group | | `Customer` | `groups`: customer group codes, `@guest` and `@no_group` |
| `product` | Product | `product` | `HostPage` | `products`: product codes |
| `product_in_taxon` | Product in taxon | `product` | `HostPage` | `taxons`: taxon codes, and `includeChildren`: a Toggle |
| `taxon` | Taxon | `taxon` | `HostPage` | `taxons`: taxon codes |
| `entry` | Entry | `entry` | `HostPage` | `entries`: entry UUIDs |
| `entry_in_category` | Entry in category | `entry` | `HostPage` | `categories`: category UUIDs |

- **Customer group.** Sylius gives a customer at most one group, so the condition checks one value: the group's code, `@guest` for a shopper who isn't signed in, or `@no_group` for one signed in without a group ([Content model](content-model.md#reserved-customer-group-values)).
- **Product in taxon** matches when the product is in any of the taxons, through any of its taxons, not only its main one. With `includeChildren`, the taxons' descendants count too.
- **Entry in category** matches an entry whose content type has categories and which is in any of them.
- **Date window** compares the time from the render context, so the preview's Date applies ([Shown to and Date](#shown-to-and-date)).

Kinds that read `HostPage` work in visibility conditions and in page scopes. The others work only in visibility conditions.

**Not shipped:** cart contents or total (promotions cover that), device (CSS covers that), currency, URL or UTM parameters, recurring times such as a weekday or an hour, and order history. Developers can add any of these as their own condition kinds, which work on blocks and page scopes, never on entries.

## An entry's audience

An [audience](../../GLOSSARY.md#visibility) decides which customers an entry shows to ([ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md)).

| Mode | Stored as | Shows to |
| --- | --- | --- |
| Everyone | `everyone` | every shopper |
| Only these | `only`, with groups | shoppers whose customer group value is in the list |
| Everyone except these | `except`, with groups | every shopper whose value isn't in the list |

- The groups are customer group codes plus `@guest` and `@no_group`.
- The audience takes effect on save, like `enabled` ([Publishing](publishing.md#what-takes-effect-on-save)), and fires `EntrySettingsChanged`.
- It's stored so SQL can filter on it: a mode plus a table of group codes beside the entry. Listings, sources, URL lookups and slots filter on it the same way they filter on channels and the date window ([Sources](sources.md#visible-queries)).

**An entry outside the shopper's audience behaves like a disabled one everywhere:**

- its URL returns 404, with no redirect to sign in
- listings and sources leave it out
- a snippet in a slot, or referenced from another block tree, renders nothing
- links to it aren't rendered

The URL's route condition runs before the firewall, so the controller checks the audience, not the condition ([URLs and SEO](urls-and-seo.md#routing)).

## Capped slots

A slot with a `cap` shows only the first `cap` matching placements, in editor order ([Slots](slots.md#capped-slots)).

- **A placement matches** when its page scope matches the page and its snippet is visible to the shopper: published, enabled, inside its date window, in the channel, translated and not held back, and in the shopper's audience.
- **Hidden blocks don't count.** A snippet whose only slide has expired still wins and shows nothing. The winners are therefore picked without rendering anything.
- **The fallback is editor content, not code:** a last placement with an empty page scope whose snippet's audience is Everyone. The admin warns when a capped slot has no such catch-all placement ([Admin](admin.md#problems)).
- There's no "most specific scope wins" rule. Editor order is the only order.

Inside a block tree, the **First match** block does the same for its children: it shows only its first child whose visibility conditions match. Its main use is a fallback that "is not one of" can't express, such as a dated campaign banner and then a default one ([Blocks](blocks.md)).

## Deleted customer groups

A deleted customer group leaves a dangling code ([ADR 0002](../adr/0002-references-by-code-and-uuid.md)), which matches nobody:

- In a visibility condition, "is one of" no longer matches through it, and "is not one of" no longer excludes anyone through it.
- In an audience, "Only these" no longer lets anyone in through it, and "Everyone except these" no longer keeps anyone out through it.

The admin flags each one as a broken reference ([Admin](admin.md#problems)). The same applies to a deleted channel, locale, product or taxon in a condition.

## Caching contract

v2.0 caches nothing the shop renders ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)), so customer-group targeting costs nothing. Sylius 1.14 already makes every shop page private because of the session.

The declarations a later cache needs ship anyway, so adding one breaks nothing written for v2.0:

- Every condition kind declares what it reads: `HostPage`, `Channel`, `Locale`, `Customer` or `Time`. A kind that declares nothing counts as reading anything, so a later cache never caches its output.
- Kinds that read `Time` implement `TimeDependentConditionKindInterface` and report their next change with `nextChange()` ([Extending](extending.md#condition-kinds)).
- The rule a later cache must keep: output that read the customer is never served from a shared cache to someone in a different customer group, and output that read the time expires at its next change.

Listings and sources report no next date boundary in v2.0.

## Shown to and Date

The builder's preview toolbar has two pickers, carried by the signed preview token ([Builder](builder.md)):

- **Shown to:** Guest, Signed in without a group, or any customer group.
- **Date:** now, or a chosen date and time.

They set the customer group value and the current time in the render context, the one place the renderer reads them ([Extending](extending.md#rendering-services)). So:

- every condition on the page follows them, including those on other snippets in slots
- audiences follow them, in slots, listings and sources
- the visible-entries query follows them, so listings in the preview match that shopper at that time
- the forced Date applies to date windows only. It doesn't run other entries' scheduled publishes.

**The edited entry ignores its own audience and date window**, as an unpublished or disabled entry already shows in its own preview. Its blocks still follow the pickers.

- Shown to starts as Guest when the audience allows guests, and otherwise as the first group the audience allows.
- Date starts as now, or as the start of the entry's date window when that's in the future.

**A snippet that loses its capped slot** still shows there in the preview, as if it won. The toolbar says which snippet shoppers like this actually see.

**Share links** fix Shown to and Date when they're created, with the same defaults. Viewers can't change them, because share links have no toolbar ([Publishing](publishing.md#share-links)).
