# Publishing

Drafts, saving, publishing now or at a set time, revisions and share links.

- **Terms:** [publishing](../../GLOSSARY.md#publishing) (draft, publish, scheduled publish, date window, revision, share link) and [entries](../../GLOSSARY.md#entries) (translation).
- **Decisions:** [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (published content stays in the rows, and a draft is JSON beside them), [ADR 0005](../adr/0005-placements-take-effect-on-save.md) (placements take effect on save), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (previews and share links are never cached).

## One workflow for every entry

Pages, snippets and collection entries all publish the same way. Things that aren't entries (placements, presets and media items) have no drafts and take effect on save ([Layouts and presets](layouts-and-presets.md), [Media](media.md)).

## Drafts

An entry has at most one [draft](../../GLOSSARY.md#publishing), shared by everyone who edits it. Shoppers keep seeing the published content until someone publishes.

- **Where things live** ([ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md)). The entry's row, its translation rows and its block tree column always hold the published content, so no shop query filters on a stage. A draft is the whole entry in the [entry format](content-model.md#entry-format), in a table of its own.
- **A never-published entry has no draft.** Saving writes straight to its rows, which no shopper can see yet, because it hasn't been published.
- **A published entry gets a draft** on the first save after a publish. The draft starts as a copy of the rows plus that change.
- **What's drafted:** the content type's fields, every translation with its fields and its shown switch, and the block tree with its layout and layout fields ([Content model](content-model.md#whats-drafted-and-what-takes-effect-on-save)).
- **Upgraders** run on drafts when they're read, like on rows ([Content model](content-model.md#schema-changes)).

### Entry states

| State | Meaning |
| --- | --- |
| Never published | No publish has happened. Saves go to the rows. |
| Published | Published, with no draft. |
| Unpublished changes | Published, with a draft. |
| Publishes at a time | A scheduled publish is waiting. The draft, or a never-published entry's rows, is locked. |
| Scheduled publish overdue | The scheduled time has passed and the publish hasn't run. |
| Scheduled publish failed | A scheduled publish was stopped when it ran ([Failed scheduled publishes](#failed-scheduled-publishes)). |

Whether a published entry shows also depends on its on-save settings. The admin shows these states and settings as status labels ([Admin](admin.md#entry-lists)).

## What takes effect on save

These settings take effect as soon as they're saved, outside the draft:

- `enabled`
- the date window
- the channels
- the audience
- "Homepage of" (pages)
- the position (content types ordered by position)
- a snippet's placements

They decide whether, where and to whom the published content shows, so changing one never publishes someone's unfinished draft. Saving one bumps the entry's `changedAt` and fires `EntrySettingsChanged` with the list of what changed, or `PlacementsChanged` for placements ([Extending](extending.md#events)).

**Unpublishing** means turning `enabled` off. The published content is kept for turning it back on.

## When an entry shows

An entry shows when it has been published, is enabled, is inside its date window, is in the channel, has a translation in the locale that isn't held back, and its audience allows the shopper. The rule, and what "not visible" means everywhere, is in [Content model](content-model.md#when-an-entry-shows).

## Saving

**Autosave.** The builder saves a moment after each change and shows "Saving…" and "Saved". There's no Save button, only Publish and Discard changes. Undo works in the browser and autosaves too ([Builder](builder.md)).

**Changes, not copies.** The builder sends the changes it made since it last loaded the draft: values by field and locale, and blocks added, moved or removed. The request carries the draft's lock token from that load. The format of the change set is internal to the builder ([Builder](builder.md#json-endpoints)).

**Merging.** When a colleague saved in the meantime:

- Changes that don't touch the same values are both applied. The response carries the colleague's changes, and the builder pulls them in.
- A real clash reaches the editor: the same field in the same locale, or an edit to a block a colleague deleted. The editor keeps theirs or takes the other, one clash at a time.

**Presence.** The builder shows who else has the entry open.

**On-save settings** are saved through their own endpoint as soon as they change, not through the change set ([Builder](builder.md#json-endpoints)).

## Validation

A draft saves even when it's invalid, so autosave never loses work. Invalid blocks are flagged in the builder's tree. The one exception is a slug that another route already matches, which is refused on save ([URLs and SEO](urls-and-seo.md#reserved-urls)).

Publish validates, refuses on errors, and points at the blocks and fields that have them. It checks:

- the Symfony Validator constraints on fields, and required fields
- each field type's own validation ([Extending](extending.md#field-types))
- the nesting rules: allowed block types, min and max, parents and needed context ([Content model](content-model.md#blocks-and-nesting))
- blocks of a type the code no longer has
- the Embed code block: while `embed_code` is off, an `embed_code` block whose key isn't already in the entry's published tree is refused, however it got into the draft ([Blocks](blocks.md#embed-code))
- the slug: unique per channel and locale, and not matched by another route ([URLs and SEO](urls-and-seo.md#routing))

Slug checks also run whenever a slug or the channels change, so the builder shows a clash before publish.

## Publishing now

A publish:

1. validates the draft, or a never-published entry's rows, and refuses on errors
2. dispatches `BeforeEntryPublish`, which a listener can veto with a message the builder shows
3. copies the draft onto the rows and deletes the draft
4. records the old slug of every translation whose slug changed, so it redirects ([URLs and SEO](urls-and-seo.md#routing))
5. writes indexed `Entries` fields to the reference table ([Collections](collections.md#categories))
6. updates the media usage index ([Media](media.md))
7. stores a [revision](#revisions)
8. bumps `changedAt`, or, in an import, sets it to the export's value ([Transfer](transfer.md#what-an-import-does))
9. after the transaction commits, dispatches `EntryPublished` with the old and new slugs

A content type can fill values on publish, as Blog post fills its date on the first publish when it's empty ([Collections](collections.md)).

**Discard changes** deletes the draft, so the entry goes back to its published content. A never-published entry has no draft, so it has nothing to discard.

## Held-back translations

Each translation has a switch, such as "Show in Danish", stored as `shown` in the entry format. It's part of the draft, so a translation goes live together with its finished text.

- A translation added after the entry was first published starts held back.
- Translations made before the first publish start shown.
- A held-back translation counts as missing: the entry's URL returns 404 in that locale, and a snippet renders nothing there ([Localization](localization.md#availability)).

## Date window

Every entry has an optional "Show from" and "Show until", stored as columns so listings and sources filter on them in SQL ([Sources](sources.md#visible-queries)). "Show from" is inclusive and "Show until" exclusive, as in the [date window condition](targeting.md#condition-kinds).

- The [date window](../../GLOSSARY.md#publishing) takes effect on save, outside the draft.
- It's checked when content renders, so it needs no worker.
- It decides only when published content shows, never what's published.
- Blocks get a date window through the `date_window` visibility condition instead ([Targeting](targeting.md#condition-kinds)).

## Scheduled publishes

An editor can [schedule a publish](../../GLOSSARY.md#publishing) of the draft for a chosen time, from the builder's top bar or the entry's overview page. It works the same whether or not the entry is already live, and for a never-published entry it schedules the first publish.

- **One per entry.** An entry has at most one scheduled publish.
- **The draft is locked** until the publish runs or is cancelled. Editors cancel it to keep editing. The time must be in the future.
- **Checked when scheduling.** Validation and `BeforeEntryPublish` run when the editor schedules, so problems show at once. They run again when the publish runs. Scheduling fires `EntryPublishScheduled`, and cancelling fires `EntryPublishCancelled`.
- **The worker.** A scheduled publish is a delayed Symfony Messenger message on Sylius's `main` transport, the way Sylius 1.14 schedules catalog promotions. The shop needs a running worker. When it runs, the publish does everything [Publishing now](#publishing-now) does, and its revision records it as scheduled.
- **Overdue.** When a scheduled publish hasn't run five minutes after its time, the admin warns that the Messenger worker may not be running, and offers Publish now and Cancel the schedule ([Admin](admin.md#entry-overview-page)). A missing worker never fails silently.

Only actions that change stored content at a set time use the worker. Date windows don't.

**Releases**, meaning bundles of entries published together, are out of v2.0.

### Failed scheduled publishes

A scheduled publish runs in the worker, when no editor is watching. If it's stopped when it runs, by a validation error such as a URL clash that appeared since scheduling, or by a listener's veto:

- it isn't retried
- the draft unlocks
- the entry is flagged as a failed scheduled publish, with the reason
- `EntryPublishFailed` fires with the reason, so projects can notify someone

The entry's overview page shows the reason and offers Schedule again ([Admin](admin.md#entry-overview-page)).

## Revisions

Every publish stores a [revision](../../GLOSSARY.md#publishing): manual, scheduled, or after an import ([Transfer](transfer.md)).

- **What it holds:** the published content in the [entry format](content-model.md#entry-format), who published it, when, and whether it was scheduled. On-save settings and placements aren't part of it.
- **Preview.** Editors preview a revision read-only, in the shop page with preview mode ([Builder](builder.md)).
- **Restore** copies the revision into the draft, replacing what the draft held, never straight to live. The editor reviews it and then publishes. Restoring is refused while a scheduled publish locks the draft.
- **Media.** A revision shows its media items' current files, not the files they had then ([ADR 0013](../adr/0013-media-items-take-effect-on-save.md)). Revisions aren't in the media usage index, so a revision whose media item was deleted gives a broken reference after a restore.
- **Upgraders** run on revisions when they're read.
- **Pruning.** `revisions.keep` revisions per entry are kept, 50 by default ([Extending](extending.md#configuration)). `setono:cms:revisions:prune` deletes older ones, and a project runs it from cron.
- There's no diff between revisions in v2.0.

## Share links

A [share link](../../GLOSSARY.md#publishing) shows one entry's draft in the shop to anyone who has it, with no admin account. It's built on the builder's preview mode ([Builder](builder.md)).

- **Created from the builder** or the entry's overview page. The editor picks a channel and a locale. Shown to and Date are fixed when the link is created, with the preview's defaults ([Targeting](targeting.md#shown-to-and-date)). For a snippet, the link also fixes the placement whose host page shows it and the sample object.
- **The URL** is the entry's URL in that channel and locale, on the channel's hostname, with a `_cms_share` query parameter holding a random token. For a snippet, it's the host page's URL. The token alone decides what's shown: the shop renders the entry's draft there even where shoppers would get a 404.
- **What it shows:** the entry's current draft, or its rows when it has no draft. Everything else on the page, such as a snippet in a slot, shows its published content.
- **Read-only.** It has no bridge and no toolbar, so viewers can't change Shown to or Date.
- **Never cached and never indexed.** Responses send `Cache-Control: no-store` and `X-Robots-Tag: noindex`.
- **The token doesn't leak.** Responses also send `Referrer-Policy: no-referrer`, so the token isn't passed on to other sites. Templates can tell they're on a share link with `cms_preview_mode()`, and leave out analytics that would record the URL ([Rendering](rendering.md#preview-mode-and-share-links)).
- **Several per entry.** Each works until it's revoked. The overview page lists them with their channel, locale, Shown to, Date and who made them, with copy and revoke ([Admin](admin.md#entry-overview-page)).

Share links don't travel in a transfer ([Transfer](transfer.md)).

## Events

| Action | Events ([Extending](extending.md#events)) |
| --- | --- |
| Creating an entry | `EntryCreated` |
| Publishing | `BeforeEntryPublish`, then `EntryPublished` |
| Scheduling a publish | `BeforeEntryPublish`, then `EntryPublishScheduled` |
| Cancelling a scheduled publish | `EntryPublishCancelled` |
| A scheduled publish running | `BeforeEntryPublish`, then `EntryPublished`, or `EntryPublishFailed` |
| Saving an on-save setting | `EntrySettingsChanged` |
| Saving placements | `PlacementsChanged` |
| Deleting an entry | `BeforeEntryDelete`, then `EntryDeleted` |

Saving a draft fires no event, because it doesn't change what shoppers see.
