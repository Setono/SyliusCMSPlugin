# Code presets are JSON files in the stored block-tree format

Developers ship presets as JSON files in the same format as a stored block tree: each block has its type, its block-type version, its field values and its children. The file also holds the preset's name, category and description and, for a starting point, its content type, layout and layout field values. A project or a bundle registers a directory of these files. Developers don't write them by hand. They compose a preset in the builder and export it, from the builder or with a console command, and another console command validates the files in CI.

Each block records its block-type version, so the upgraders that bring stored trees up to date also upgrade old preset files when they're inserted. A text value can be a translation key instead of a map of locales, for plugins that don't know a store's locales. When the preset is inserted, each of the entry's locales gets the key's translation if there is one, and the other locales stay empty, so they count as untranslated.

We chose this because a preset is data: often a deep tree with many field values. The builder is the easiest place to build one, and the exported file has the same shape as a preset an editor saves, so one code path inserts both.

## Considered Options

- **PHP classes** that build the tree with a fluent API and are registered by attribute, like block types. They're type-checked and easy to refactor, but every preset is written by hand. They're also a second way of writing down a tree, which has to be kept in step with the stored format.

## Consequences

- The stored block-tree format becomes a contract, because projects and plugins commit files in it. Upgraders handle changes to block fields, but a change to the format itself is a breaking change.
- Code presets are read-only in the admin. To change one, a developer inserts it, edits it, and exports it again.
- Editor presets use the same format, which makes it the natural candidate when presets are moved between environments.
