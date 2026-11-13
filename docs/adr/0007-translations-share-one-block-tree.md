# Translations share one block tree and store their values in it

An entry has one block tree for all its translations. A translatable field stores its values inside the tree's JSON, as a map from locale to value, and a shared field stores one value. A block that belongs only in some locales is hidden in the others with a locale visibility condition. Translatable fields specific to one content type sit on a translation subclass that mirrors the entry's single table inheritance, as columns, the way Sylius product translations work.

We share the tree because most block-based CMSs do (Storyblok doesn't even allow a blocks field to be translated), and because a tree per locale means repeating every structural edit in every locale while the copies drift apart. We keep the values inline so an entry stays one document to read, version, diff, upgrade and transfer (ADR 0001), and deleting a block deletes its translations with it.

## Considered Options

- **A tree per locale**, as in Wagtail, or Payload with a localized blocks field. This gives the most freedom, but every structural edit has to be repeated in each locale.
- **Letting each content type choose** between a shared tree and a tree per locale. That means two code paths through the editor, fallback, transfer and the future API.
- **Splitting the values per locale**, as Sulu 3 does: the shared tree stays on the entry, and each translation row holds its own values, keyed by block key. This fits Sylius's translation rows, and translators only touch their own locale. But the two documents have to stay consistent, deleted blocks leave orphaned values behind, and upgraders have to rewrite both.
- **Storing type-specific translatable entry fields as JSON** on a single translation class. This needs no class per content type, but those fields can't be queried or sorted on.

## Consequences

- Fallback works per field, which Sylius doesn't do. An empty value falls back through the fallback chain, while a Sylius product falls back only when its whole translation is missing.
- Block type upgraders see translatable fields as locale maps. Making a field translatable, or shared, means bumping the block type's version and writing an upgrader.
- Translators of different locales save to the same column, so saving needs optimistic locking.
- A translated value also records the locale it was translated from and a fingerprint of that value, so it can be flagged as outdated when its source changes.
