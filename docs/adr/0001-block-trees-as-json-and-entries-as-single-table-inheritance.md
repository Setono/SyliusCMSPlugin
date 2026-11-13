# Block trees are JSON on their entry, and entries use single table inheritance

An entry's block tree is stored as one JSON column on the entry, not as a row per block. Every block carries a stable key, its block type, and the version of that block type it was saved with. Block types ship upgraders that bring older data up to date when it is read, and a console command rewrites stored data in bulk. Rich text fields are stored inside the tree as Tiptap/ProseMirror JSON, not as HTML.

Entries use Doctrine single table inheritance. All entries share one table, and each content type is an entity subclass whose fields are real, nullable columns. The plugin builds the discriminator map from the registered content types, so projects never edit the plugin's mapping.

We split it this way because the two kinds of data behave differently. A block tree nests and is always loaded whole, so one JSON document is cheap to read, version, diff and transfer, as in Sulu 3, Wagtail and Payload. Entry fields are flat and get queried (publish dates, cities, coordinates), so they belong in columns that can be indexed.

## Considered Options

- **A row per block** (as in Craft). Each block gets its own identity and can be queried on its own, but you get many rows and joins, and versioning a whole tree is harder.
- **Type-specific entry fields as JSON on one generic entry model.** No migration is needed per content type, but querying and sorting on those fields is weak.
- **Class table inheritance.** You get NOT NULL constraints and no sparse table, but every query across content types joins every subtype table.

## Consequences

- Required entry fields are enforced by validation, not by the database, because subclass columns must be nullable.
- Every new content type alters the shared entry table, so content types need a column-prefix convention to avoid collisions.
- Changing a block type's fields means bumping its version and writing an upgrader. Changing a content type's fields means writing a Doctrine migration.
