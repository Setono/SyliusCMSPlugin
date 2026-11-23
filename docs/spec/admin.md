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
- **A collection's item** lists its entries and its categories, switched by buttons such as All, Posts and Categories. The item and its lists are named by the collection's name, `setono_sylius_cms.collection.<code>` such as Blog, or else by its plural, and the buttons by the content types' plurals.
- **There's no Slots screen.** Slots are reached through a snippet's placements.

## Overview

The hub's home page has four panels:

- **Needs attention:** the errors and warnings across the store ([Problems](#problems)), grouped by kind. Each links to the place where it's fixed.
- **Coming up:** scheduled publishes that wait, and the starts and ends of date windows, in time order, the first 10. A start or end is listed only for an entry that's enabled, and published or scheduled to publish by then (a scheduled publish that hasn't failed, at the start or end or before it), as only those change what shoppers see.
- **Translations:** completeness per channel and locale, with a link to the entries that aren't complete ([Localization](localization.md#completeness-and-outdated-flags)).
- **Drafts in progress:** the 10 entries edited last, of those with unpublished changes and those never published, each labelled "Unpublished changes" or "Never published", with a link to all of them in All entries, filtered on both states.

The panels and the Content badge leave out the entries of content types switched off in `disabled.content_types`, whose overview pages answer 404, as the lists do ([Collections](collections.md#what-ships)).

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

- **Filters:** search, status and channel. The status filter offers Needs attention, Unpublished changes or never published, Unpublished changes, Never published, Scheduled, and Disabled or outside its date window.
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
- **Share links:** each link's channel, locale, Shown to, Date and who made it, and for a snippet the placement whose host page shows it and the sample object, with copy and revoke, plus New ([Publishing](publishing.md)). A snippet's New picks one of its placements, or a plain page.
- **URLs** (content types with their own URL): the URL per locale, the old slugs that redirect, and for a page "Homepage of" per channel, showing the page that's each channel's homepage now.
- **Used in** (snippets): the entries whose block trees reference it, each with where: in its published content, in its draft, or published but removed in the draft.
- **Translations:** the chips. Translating happens in the builder.

### Placements

A snippet's placements are managed here, not in the builder, and take effect on save ([ADR 0005](../adr/0005-placements-take-effect-on-save.md)).

- **Each placement** shows its slot, its position, such as "1 of 3 · capped at 1", its page scope ([Slots](slots.md#page-scopes)) and who it wins for: its snippet's audience, "where no placement above it shows" once it's past the cap, or no one when as many catch-all placements as the cap come before it. Placements can be added, edited, removed and put in order.
- **Edit page scope** opens a dialog that edits the scope's conditions as the builder edits visibility conditions, offering only the kinds that read `HostPage` and whose needs the slot provides. Save takes effect at once and comes back to the overview page. A scope that can't be saved is kept as it was, and the page says why.
- **Adding a placement** offers only slots that provide at least the snippet's declared context ([Slots](slots.md#snippets-and-their-context)).
- **Order…**, on every placement in a slot the code declares, capped or not, opens a dialog with all the slot's placements, from every snippet, each with its snippet, page scope and audience, to drag into order or move with arrows. It also shows the slot's warnings: no catch-all placement (a capped slot only), or hidden by a CMS homepage. Save order takes effect at once, and an order made before the slot's placements changed, such as in another tab, is refused.

## Into and out of the builder

- The builder opens from the overview page, and straight after an entry is created.
- Its back button, and Esc, return to where it was opened from.
- The builder's entry settings have a tab that points back to the overview page for publishing, revisions, share links and placements ([Builder](builder.md)). Publish also sits in the builder's top bar.

## Create dialog

Creating an entry from the Overview, All entries or a list:

1. **Content type**, when creating from the Overview or All entries.
2. **Title** and, for a content type with its own URL, **slug**, generated from the title until the editor changes it ([URLs and SEO](urls-and-seo.md)).
3. **Layout**, from wireframe cards, when the content type has more than one ([Layouts and presets](layouts-and-presets.md)).
4. **Starting point:** Blank, or one of the content type's starting points, shipped or saved by an editor, as cards with their thumbnails. The default starting point is preselected, otherwise Blank. A starting point with blocks of types that no longer exist, or saved with a newer version than the code's, is disabled and shows why. A starting point brings its own layout, so the layout cards are hidden while one is picked and only apply to Blank. A content type without a block tree, or without starting points, has no step.
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
- **Editor presets** can be renamed, recategorised, deleted and downloaded as code preset files. Their blocks are changed by saving over them in the builder ([Layouts and presets](layouts-and-presets.md)).
- **A preset with blocks of types that no longer exist** is flagged.

## Transfer

- **Export:** pick entries, editor presets and media folders, or everything. The screen lists the snippets and media items added because the picked records use them. **Download** gives a zip ([Transfer](transfer.md)).
- **Import:** upload a zip, then see the dry-run plan: create, update, unchanged or left alone per record, the conflicts, homepage changes and unresolved references.
  - Records with a draft or a scheduled publish here, or changed here since the export, are conflicts and block the import. A checkbox overwrites them, which discards their drafts.
  - An option publishes everything imported.
  - **Import** confirms, runs on the Messenger worker and shows its progress. The uploaded zip is kept in the plugin's storage, so a worker on another server can read it ([Transfer](transfer.md#who-runs-it)). The result links to the imported drafts to review and publish, or lists why the import refused.
  - **Each import has a page**, which shows its progress while the worker checks or runs it and updates itself when that's done, then the plan, then the result. **Cancel** abandons an import before it's confirmed and deletes its zip. An import the worker kept failing on shows the error it failed with. The Transfer screen lists the latest imports, each linked to its page.

## Problems

The admin flags problems on the entry, in the lists' problem marker, on the Overview and in the Content badge.

**Errors:**

- a scheduled publish that's overdue
- a scheduled publish that failed, with its reason
- a broken reference: a deleted product, taxon, entry, snippet or media item in a field or link, a deleted customer group in a condition or an audience, a deleted channel, locale, product, taxon, entry or media item in a condition ([Targeting](targeting.md#deleted-records-in-conditions)), or a deleted category ([Content model](content-model.md#references))
- a Snippet block through which its snippet would render inside itself, directly or through other snippets' published trees, which publish refuses, so it comes from imported or older data ([Rendering](rendering.md#rendering-a-block-tree))

**Warnings:**

- an entry whose layout was removed from code or disabled
- an entry URL that a Sylius or app route takes ([URLs and SEO](urls-and-seo.md#routing))
- a category archive whose block tree no longer holds an Entries block ([Collections](collections.md))
- a capped slot without a catch-all placement, one with an empty page scope whose snippet is published, enabled and for everyone, on each snippet placed in it ([Targeting](targeting.md#capped-slots))
- a page that's the homepage of a channel it isn't in, where Sylius's own homepage shows ([URLs and SEO](urls-and-seo.md#homepage))
- a placement in a slot on Sylius's own homepage, such as `store_homepage`, in a channel whose homepage is a CMS page, published or not ([URLs and SEO](urls-and-seo.md#homepage))
- a placement or slot that the code no longer declares, or a block type, context key or layout that was removed
- a block's visibility condition of a kind that was removed, which never matches, so the block stays hidden ([Targeting](targeting.md#condition-kinds))
- a field of a block or of the layout bound to a property the code no longer declares (`unknown_bindable_property`), which shows its fallback ([Slots](slots.md#bindings)), and a list filled by a source the code no longer has (`unknown_source`) ([Sources](sources.md))
- a preset with blocks of removed types

Missing translations and missing alt text are shown by the translation chips and the missing-alt flags, and aren't counted as problems.

The entry's overview page finds its problems as they are now. The lists' marker, the Needs attention filter and the Content badge read counts stored with each entry, so no admin page goes through every entry: a write counts the entries it touches again, a deleted or added customer group the entries that name it, a media item, entry, product, taxon, channel or locale deleted, or given another UUID or code, the entries that may refer to it by its old or new one (any other write of them counts nothing, so a product import runs no extra query), and a deploy that changes the block types, layouts, content types or condition kinds every entry, on the next admin page. Overdue scheduled publishes are found as of now. A URL that a route added later takes is counted when `setono:cms:check-routes` runs ([Extending](extending.md#console-commands)).
