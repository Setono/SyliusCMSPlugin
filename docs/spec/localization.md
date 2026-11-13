# Localization

Translations, the fallback chain, where an entry is available, channels, and the builder's translation aids.

- **Terms:** [entries](../../GLOSSARY.md#entries) (translation, fallback chain, slug) and [content structure](../../GLOSSARY.md#content-structure) (field).
- **Decisions:** [ADR 0007](../adr/0007-translations-share-one-block-tree.md) (translations share one block tree and store their values in it), [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (held-back translations are drafted).

## One shared block tree

An entry has one block tree for all its [translations](../../GLOSSARY.md#entries). Only field values differ per locale, and a structural edit in one locale changes every locale.

- A block that belongs only in some locales, such as a German legal notice, is hidden in the others with a `locale` visibility condition ([Targeting](targeting.md#condition-kinds)).
- Deleting a block deletes its values in every locale with it.

## Translatable and shared fields

Translatability is declared in code, per field ([Content model](content-model.md#fields)):

- **Translatable by default:** Text, Textarea, Rich text, and a Link's label and external URL.
- **Shared by default:** references (products, taxons, entries, snippets, media items), list fields and their sources, numbers, choices, toggles and dates.
- A block type or layout overrides the default per field with `translatable:`, such as a banner slide's image when text is baked into it.

**Values are stored inline.** A translatable field in the block tree stores a map from locale to value ([Content model](content-model.md#field-values)), so an entry stays one JSON value to read, version, diff, upgrade and transfer. Making a field translatable, or shared, means bumping the block type's version and writing an upgrader ([Content model](content-model.md#schema-changes)).

**Entry fields** that are translatable sit on the content type's translation subclass, as columns, mirroring the entry's single table inheritance ([Extending](extending.md#content-types)). Every translation has a title, and a content type with its own URL adds a slug, a meta title and a meta description.

**Saving needs optimistic locking**, because translators of different locales write to the same block tree. How the builder merges concurrent saves is in [Publishing](publishing.md#saving).

## Which locales an entry has

- The admin offers a translation for each locale of the entry's channels.
- An entry **has** a translation in a locale once that translation has a title.
- Each translation has a **shown** switch, which holds it back when it's off ([Publishing](publishing.md#held-back-translations)). It's part of the draft, so a translation goes live together with its finished text.

## Availability

An entry shows only in locales where it has a translation that isn't held back ([Content model](content-model.md#when-an-entry-shows)). In any other locale:

- its URL returns 404, as a Sylius product does ([URLs and SEO](urls-and-seo.md#routing))
- a snippet in a slot, or referenced from another block tree, renders nothing
- listings leave it out, and sources don't return it ([Sources](sources.md#visible-queries))
- links to it aren't rendered ([References to unavailable entries](#references-to-unavailable-entries))

Whole entries never fall back to another locale. Moving to fallback for whole entries later would change a rendering rule, not the data. The admin's "Where it shows" grid makes availability visible per channel and locale ([Admin](admin.md#entry-overview-page)).

## Fallback chain

Fallback works per field. A translatable field that's empty in the current locale falls back through the [fallback chain](../../GLOSSARY.md#entries):

1. the current locale
2. the locales configured for it under `setono_sylius_cms.fallback_locales`, in order
3. Sylius's fallback locale, the `locale` parameter that product translations fall back to

```yaml
setono_sylius_cms:
    fallback_locales:
        de_CH: [de_DE]
        fr_CH: [fr_FR, de_DE]
```

- **An empty value counts as missing.** An empty string, an empty rich text document and a missing locale key all fall back.
- **To leave something out in one locale**, the editor hides the block with a `locale` condition instead of emptying the field.
- **Entry fields fall back the same way.** The meta title falls back to the title only after the chain finds no meta title ([URLs and SEO](urls-and-seo.md)). Slugs never fall back: a URL exists only in locales with a slug.
- **Media alt text** on a media item falls back through the same chain ([Media](media.md)).
- A binding's fallback is a different thing: it covers an empty bound value, not a locale ([Slots](slots.md#bindings)).

## References to unavailable entries

A link or reference to an entry that isn't visible in the current channel and locale, or to the shopper, isn't shown:

- a Link field, a Button or a Link block pointing to it isn't rendered
- a rich-text link to it renders as plain text
- a Snippet block referencing it renders nothing
- a hand-picked Entries list leaves it out

The admin flags each one, as it flags a broken reference ([Admin](admin.md#problems)). The builder flags links to entries that aren't available in the locale being edited, in the tree and in the preview.

## Channels

Entries are channel-aware like Sylius products ([Content model](content-model.md#entries)):

- Each entry has a set of channels. A new entry starts in every channel, and in a store with one channel the picker is hidden.
- The channels take effect on save, outside the draft ([Publishing](publishing.md#what-takes-effect-on-save)).
- The block tree and translations are shared across channels. There are no per-channel copies and no per-channel overrides of field values.
- Content that differs per channel inside one tree uses `channel` visibility conditions on blocks ([Targeting](targeting.md#condition-kinds)).
- A URL is unique per channel and locale, so entries with no channel in common can share one ([URLs and SEO](urls-and-seo.md)).

## In the builder

The builder edits one locale at a time ([Builder](builder.md)):

- **Locale switcher.** It sits in the top bar and shows each locale's untranslated and outdated counts. Switching reloads the preview in that locale.
- **"All languages" badge.** Shared fields carry it, so editors know an edit changes every locale.
- **Fallback placeholders.** An empty translatable field shows its fallback value as a placeholder, with "Copy it here". Typing over a fallback value in the preview replaces it with a translation for the current locale.
- **Outdated fields** show the current source text, with "Mark as up to date".
- **A banner** shows when the entry has no translation in the current locale.

## Completeness and outdated flags

Two aids ship in v2.0. Both read the [translation state](content-model.md#translation-state) stored with each value.

- **Completeness.** For each locale the entry has, the number of translatable values that are untranslated: missing or empty there while another locale has one. It shows in the builder's locale switcher, on the entry's overview page and as translation chips in the entry lists ([Admin](admin.md#entry-lists)). The Overview shows completeness per channel and locale across the store ([Admin](admin.md#overview)).
- **Outdated flags.** A translated value records the locale it was translated from and a fingerprint of that value. When the source value changes, the translated value is flagged as outdated until someone updates it or marks it as up to date.

Code presets whose text values are translation keys leave the locales without a translation empty, so they count as untranslated ([Layouts and presets](layouts-and-presets.md)).

Missing translations are shown by these counts and chips. They aren't counted as problems.

## A future translator

There's no AI translation, and no translation export or import, in v2. The per-field translation state (untranslated, outdated) is the seam a future translator fills, AI or not, through `TranslationStateInterface` ([Extending](extending.md#translation-state)). A translator records the fingerprint of the source it actually translated, so a source that changes while a translation is in flight leaves the result outdated.
