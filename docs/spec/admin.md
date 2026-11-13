# Admin

How the CMS is organised and navigated in the Sylius admin: the Content hub, entry lists and overview pages, the create dialog, the library screens and the problems the admin flags.

- **Terms:** [entries](../../GLOSSARY.md#entries), [shop pages](../../GLOSSARY.md#shop-pages), [visibility](../../GLOSSARY.md#visibility), [media](../../GLOSSARY.md#media), [editing](../../GLOSSARY.md#editing), [publishing](../../GLOSSARY.md#publishing), [transfer](../../GLOSSARY.md#transfer).
- **Decisions:** [ADR 0005](../adr/0005-placements-take-effect-on-save.md) (placements take effect on save), [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (drafts), [ADR 0010](../adr/0010-entries-have-an-audience-not-visibility-conditions.md) (audience), [ADR 0013](../adr/0013-media-items-take-effect-on-save.md) (media items take effect on save), [ADR 0015](../adr/0015-entry-urls-are-matched-last-by-a-route-that-looks-them-up.md) (entry URLs), [ADR 0016](../adr/0016-content-moves-between-environments-as-export-files-and-imports-land-in-drafts.md) (transfer).

The routes are in [Extending](extending.md#routes). Every admin user can do everything, because Sylius 1.x OSS has no roles to build on.

## Navigation

- **The Sylius admin menu** (`sylius.menu.admin.main`) gets one item, **Content**. Its badge counts the errors and warnings that need attention ([Problems](#problems)).
- **Content opens a hub** with its own side menu:
  - **Overview**, the hub's home.
  - **Entries:** All entries, Pages, Snippets, then one item per collection, each with a count. The shipped Blog and FAQ come first, then a project's own, such as Store locations. A disabled content type has no item.
  - **Library:** Media, Presets and Transfer.
- **A collection's item** lists its entries and its categories, switched by buttons such as All, Posts and Categories.
- **There's no Slots screen.** Slots are reached through a snippet's placements.

## Overview

The hub's home page has four panels:

- **Needs attention:** the errors and warnings across the store ([Problems](#problems)), grouped by kind. Each links to the place where it's fixed.
- **Coming up:** scheduled publishes, and date windows that start or end, in time order.
- **Translations:** completeness per channel and locale, with a link to the entries that aren't complete ([Localization](localization.md#completeness-and-outdated-flags)).
- **Drafts in progress:** entries with unpublished changes, newest first.

## Entry lists

Every list (All entries, Pages, Snippets and each collection) has the same columns:

| Column | Shows |
| --- | --- |
| Title | The title in the admin user's locale, with the URL and categories below it. |
| Type | The content type. Only in All entries. |
| Status | The [status labels](#status-labels). |
| Translations | A [translation chip](#translation-chips) per locale. |
| Last edited | When and by whom. |
| Problems | A marker when the entry has an error or warning. |

- **Filters:** search, status and channel. The status filter offers Needs attention, Unpublished changes, Never published, Scheduled, and Disabled or outside its date window.
- **Ordered content types**, such as FAQ items, are dragged into order in their list. The position takes effect on save ([Content model](content-model.md#whats-drafted-and-what-takes-effect-on-save)).
- **A row** opens the entry's overview page.
- A draft's values, such as a changed title, are read from its JSON, so lists can't sort or filter on them in SQL ([ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md)).

### Status labels

- Published
- Unpublished changes
- Never published
- Publishes at a time (a scheduled publish)
- Scheduled publish overdue
- Scheduled publish failed
- Disabled
- The date window: Shows from–to, or Ended
- A translation held back
- The audience, when it isn't Everyone

### Translation chips

One chip per locale of the entry's channels: complete, N untranslated, N outdated, held back, or no translation (struck through).

## Entry overview page

Every entry has one, between its list and the builder.

- **Header:** the title, content type, URL and status labels, with **Open in builder** and **Share draft**.
- **Publishing:** the draft's state and its actions: publish, schedule, and cancel a schedule ([Publishing](publishing.md)).
  - An overdue scheduled publish warns that the Messenger worker may not be running, and offers Publish now and Cancel the schedule.
  - A failed scheduled publish shows its reason and offers Schedule again.
- **Problems:** the entry's errors and warnings, each with a way to fix it.
- **Placements** (snippets): see [Placements](#placements).
- **Where it shows:** a grid of the store's channels and the locales of each. Each cell says Live, or why not: not in the channel, no translation, held back, disabled, not published yet, publishes at a time, ended, shows from a date, or hidden by a Sylius route. The audience shows below the grid.
- **Revisions:** who published each and when. Each can be previewed read-only, or restored into the draft.
- **Share links:** each link's channel, locale, Shown to, Date and who made it, with copy and revoke, plus New ([Publishing](publishing.md)).
- **URLs** (content types with their own URL): the URL per locale, the old slugs that redirect, and for a page "Homepage of" per channel, showing the page that's each channel's homepage now.
- **Used in** (snippets): the entries whose block trees reference it.
- **Translations:** the chips. Translating happens in the builder.

### Placements

A snippet's placements are managed here, not in the builder, and take effect on save ([ADR 0005](../adr/0005-placements-take-effect-on-save.md)).

- **Each placement** shows its slot, its position, such as "1st of 3 · capped at 1", its page scope ([Slots](slots.md#page-scopes)) and who it wins for. Placements can be added, edited and removed.
- **Adding a placement** offers only slots that provide at least the snippet's declared context ([Slots](slots.md#snippets-and-their-context)).
- **For a capped slot**, **Order…** opens a dialog with all the slot's placements, from every snippet, to drag into order. It also shows the slot's warnings: no catch-all placement, or hidden by a CMS homepage.

## Into and out of the builder

- The builder opens from the overview page, and straight after an entry is created.
- Its back button, and Esc, return to where it was opened from.
- The builder's entry settings have a tab that points back to the overview page for publishing, revisions, share links and placements ([Builder](builder.md)). Publish also sits in the builder's top bar.

## Create dialog

Creating an entry from the Overview, All entries or a list:

1. **Content type**, when creating from the Overview or All entries.
2. **Title** and, for a content type with its own URL, **slug**, generated from the title until the editor changes it ([URLs and SEO](urls-and-seo.md)).
3. **Layout**, from wireframe cards, when the content type has more than one ([Layouts and presets](layouts-and-presets.md)).
4. **Starting point:** Blank, or one of the content type's starting points, shipped or saved by an editor. The default starting point is preselected, otherwise Blank. A starting point with blocks of types that no longer exist is disabled and shows why.
5. **Channels**, all preselected. The picker is hidden in a store with one channel.
6. **Create and open the builder.** The entry starts never published.

## Media library

- **Folders:** a folder tree with All media and Unsorted at the top. Folders nest, and each media item sits in one folder, or in Unsorted ([Media](media.md#folders)).
- **Filters:** search (name, filename and alt text), kind (image, video, document), missing alt text, and not used.
- **The grid** shows each media item with its usage count and a missing-alt flag.
- **A selected media item** shows its focal point, name, folder, credit, alt text per locale and its **Used in** list, with links. Its actions are Replace file, Move and Delete.
  - **Replace file** confirms where the media item is used and that the change takes effect everywhere at once, keeping its alt text and focal point ([ADR 0013](../adr/0013-media-items-take-effect-on-save.md)).
  - **Delete** is disabled while the media item is in use.
- **Uploading** works by drag and drop, many files at once, into the open folder. A file identical to an existing media item offers that media item, and can still be uploaded as a copy.
- **In the builder**, media fields open the same library as a picker.

## Presets

- **Two tabs:** starting points and block presets.
- **Code presets** are read-only and marked as coming from code.
- **Editor presets** can be renamed, recategorised and deleted. Their blocks are changed by saving over them in the builder ([Layouts and presets](layouts-and-presets.md)).
- **A preset with blocks of types that no longer exist** is flagged.

## Transfer

- **Export:** pick entries, editor presets and media folders, or everything. The screen lists the snippets and media items added because the picked records use them. **Download** gives a zip ([Transfer](transfer.md)).
- **Import:** upload a zip, then see the dry-run plan: create, update, unchanged or left alone per record, the conflicts, homepage changes and unresolved references.
  - Records with a draft or a scheduled publish here, or changed here since the export, are conflicts and block the import. A checkbox overwrites them, which discards their drafts.
  - An option publishes everything imported.
  - **Import** confirms, runs on the Messenger worker and shows its progress. The result links to the imported drafts to review and publish.

## Problems

The admin flags problems on the entry, in the lists' problem marker, on the Overview and in the Content badge.

**Errors:**

- a scheduled publish that's overdue
- a scheduled publish that failed, with its reason
- a broken reference: a deleted product, taxon, entry, snippet or media item in a field or link, a deleted customer group in a condition or an audience, or a deleted category ([Content model](content-model.md#references))

**Warnings:**

- an entry whose layout was removed from code
- an entry URL that a Sylius or app route takes ([URLs and SEO](urls-and-seo.md#routing))
- a category archive whose block tree no longer holds an Entries block ([Collections](collections.md))
- a capped slot without a catch-all placement ([Targeting](targeting.md))
- a placement in a homepage stock slot that a CMS homepage hides ([URLs and SEO](urls-and-seo.md#homepage))
- a placement or slot that the code no longer declares, or a block type, context key or layout that was removed
- a preset with blocks of removed types

Missing translations and missing alt text are shown by the translation chips and the missing-alt flags, and aren't counted as problems.
