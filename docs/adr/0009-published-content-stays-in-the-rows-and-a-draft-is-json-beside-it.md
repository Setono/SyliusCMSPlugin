# Published content stays in the entry's rows, and a draft is a JSON document beside them

An entry has at most one draft, shared by everyone who edits it. The entry's row, its translation rows and its block tree column always hold the published content. A draft of a published entry is the whole entry (its fields, translations and block tree) as one JSON document in a table of its own, and publishing copies it onto the rows. An entry that has never been published has no draft: saving writes straight to its rows, which no shopper can see yet. Every publish stores a revision in the same JSON format, and restoring a revision copies it into the draft, never straight to the rows.

Whether an entry is enabled, its date window, its channels and its audience take effect on save, outside the draft, the way placements do (ADR 0005). They decide whether, when, where and to whom the published content shows, so taking a page down never publishes someone's unfinished draft. Holding back a translation is different: that switch is part of the draft, so a translation goes live together with its finished text.

We chose this so that every shop query (collection listings, sources, menus, URL lookup) only ever sees published content. No query has to filter on a stage, and the single table from ADR 0001 stays as it is. One JSON format then serves drafts, revisions, share links and content transfer.

## Considered Options

- **Two rows per entry, one per stage**, as in Sulu 3. Drafts could be queried in SQL, but every shop query, source and listing would have to filter for the live stage, and an entry's identity would move to a separate table.
- **Rows that hold the draft, with the shop rendering a published JSON document.** Collection listings and sources query columns, so they'd see unpublished values.
- **A draft per editor.** That's close to branching, and publishing would have to reconcile the drafts.
- **`enabled` inside the draft.** Taking a page down would then publish pending changes with it, unless a separate unpublish action bypassed the draft.

## Consequences

- The admin can't filter or sort on a draft's values in SQL. Lists read a draft's values, such as a changed title, from its JSON.
- Drafts and revisions hold entry fields as JSON, not as columns. A change to a content type's fields therefore needs an upgrade for stored drafts and revisions as well as a Doctrine migration, and block type upgraders run on them as they do on the rows.
- A share link renders a draft by building an entry from its JSON that Doctrine doesn't manage.
- The draft has its own optimistic lock. The builder autosaves the changes it made since it last loaded the draft, and the server merges saves that don't touch the same values. Only a real clash reaches the editor.
- A scheduled publish locks the draft until it runs. It's a delayed Messenger message on Sylius's main transport, so the shop needs a running worker, as Sylius's own scheduled catalog promotions do. The date window needs no worker, because it's checked when content renders.
