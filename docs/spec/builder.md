# Builder

The full-screen [builder](../../GLOSSARY.md#editing) where every entry is edited: its shell, how editors work in it, preview mode and the bridge, and the JSON endpoints behind it.

- **Terms:** [editing](../../GLOSSARY.md#editing), [content structure](../../GLOSSARY.md#content-structure), [entries](../../GLOSSARY.md#entries), [shop pages](../../GLOSSARY.md#shop-pages), [publishing](../../GLOSSARY.md#publishing).
- **Decisions:** [ADR 0008](../adr/0008-the-builder-is-a-react-app-with-a-twig-preview-in-a-same-origin-iframe.md) (a React app with a Twig preview in a same-origin iframe), [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (drafts), [ADR 0007](../adr/0007-translations-share-one-block-tree.md) (one shared tree), [ADR 0011](../adr/0011-layouts-are-interchangeable-frames-with-fields-in-the-block-tree.md) (layouts), [ADR 0006](../adr/0006-bindings-use-declared-properties.md) (bindings), [ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md) (Embed code placeholder), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (preview responses).

## Opening and leaving

- The builder opens from the entry's overview page, and straight after an entry is created ([Admin](admin.md#create-dialog)). Its route is `setono_sylius_cms_admin_builder` ([Extending](extending.md#routes)).
- Its back button returns to where it was opened from, and Esc does the same.
- Every entry is edited here, including entries whose content type has no block tree. For those, the tree pane is hidden, and the preview shows the entry's page when it has its own URL.

## The shell

The builder is a full-screen surface with its own layout, not a form inside the Sylius admin. It has a top bar and three panes.

### Top bar

From left to right:

- **Back**, to where the builder was opened from.
- **The entry's title and status labels**, the same labels the entry lists use ([Admin](admin.md#entry-lists)).
- **Locale switcher.** The builder edits one locale at a time, and the preview renders that locale. Each locale shows its untranslated and outdated counts ([Localization](localization.md#completeness-and-outdated-flags)).
- **Preview channel**, one of the entry's channels.
- **For a snippet:** the placement and the sample object pickers ([Snippet preview](#snippet-preview)).
- **Shown to** (Guest, Signed in without a group, or any customer group) and **Date** (now, or a chosen date and time) ([Targeting](targeting.md)).
- **Desktop or mobile** width for the preview.
- **Undo and redo**, which work in the browser on the working copy.
- **Save state:** "Saving…" while an autosave is in flight, then "Saved". There's no Save button.
- **Who else has the entry open.**
- **Publish**, with a menu to schedule the publish instead, and **Discard changes** when the entry has a draft ([Publishing](publishing.md)).

### Left pane: the block tree

- **The entry's settings** sit at the top of the tree. Selecting them shows them in the right pane.
- **Rows can be dragged** to reorder and nest blocks. A drop that the block types don't allow is refused ([Content model](content-model.md#blocks-and-nesting)).
- **⌘-click** (Ctrl-click elsewhere) selects several blocks, for moving, duplicating, deleting or saving them as a preset together.
- **Blocks hidden in the preview's channel or locale** by a channel or locale condition show struck through, with an eye-slash icon. Blocks hidden by other conditions under the current Shown to and Date drop out of the preview the same way.
- **Links to entries** that aren't visible in the current channel and locale are flagged in the tree and in the preview.
- **Invalid blocks** are flagged in the tree, with the error in the right pane.

### Centre: the preview

- The preview is the real shop page in an iframe ([Preview mode](#preview-mode)). For an entry with its own URL that's its page, and for a snippet the host page of one of its placements.
- **Selection is linked both ways.** Clicking a block in the preview selects it in the tree and the right pane, and selecting it in the tree scrolls the preview to it.

### Right pane: the selection

With a block selected, the right pane shows:

- its **path** in the tree, each step selectable
- its **actions**: move, duplicate, save as preset (★) and delete
- its **block style**, when its block type offers any
- its **fields**, each rendered from its field schema by the field type's widget ([Extending](extending.md#widgets))
- its **children**, with + to add one
- its **visibility conditions** ([Targeting](targeting.md#condition-kinds))

With nothing selected, or the entry's settings selected, it shows the settings in three tabs:

- **Settings:** title, layout (with a warning when its layout was removed from code), categories (deleted ones flagged), channels, enabled, date window, audience, "Homepage of" for pages, and the host-page context for snippets.
- **URL & SEO** for a content type with its own URL: slug (with its conflict errors), a warning when a Sylius route takes the URL, old slugs, meta title, meta description and "Hide from search engines".
- **More**, which links to the entry's overview page for publishing history, revisions, share links and placements ([Admin](admin.md#entry-overview-page)).

Entry fields that the current layout doesn't show are marked "Not shown in this layout" ([Layouts and presets](layouts-and-presets.md)). Settings that take effect on save, such as enabled or the channels, say so beside the field, and saving them doesn't touch the draft ([Content model](content-model.md#whats-drafted-and-what-takes-effect-on-save)).

## Working in the preview

- **Typing in place.** Fields printed with `cms_field()` ([Extending](extending.md#twig)) can be edited directly in the preview: text, textareas and rich text. The field in the right pane follows. Bound values can't be typed in place. Typing over a fallback value replaces it with a translation for the current locale.
- **+ between blocks** adds a block at that spot, through the block picker.
- **The selected block's toolbar** in the preview has select parent, move, duplicate, save as preset, settings and delete.
- **Embed code blocks** show a placeholder in the preview, so third-party scripts don't run inside the builder ([ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md)).
- **Empty images** show a neutral grey box at the image's aspect ratio, in the preview and the preset browser ([Blocks](blocks.md)).

## Block picker

The picker opens from + in the tree, the preview or a block's children.

- **It lists only what fits** that spot: the block types the parent or the content type allows, whose `parents` allows the spot, and whose needed context the entry provides or the snippet declares. Each has its thumbnail, and the list has a search.
- **Left-out types are named with the reason** when the reason is context, such as "needs a product page".
- **Disabled block types** ([Extending](extending.md#configuration)) aren't offered. Blocks of those types already in the tree keep rendering.
- **A Presets tab** lists code presets and editor presets that fit the spot, grouped by category, with search and a live render in the current preview context. Presets left out are named with the reason, the same way ([Layouts and presets](layouts-and-presets.md)).
- **A container that allows only one block type**, such as Columns or Accordion, adds that block straight away instead of opening the picker.

## Snippet references

- A Snippet block renders the referenced snippet in place, marked as shared.
- **Edit snippet** opens the snippet in the builder, with a way back to the entry.
- **Detach to edit here** replaces the reference with a copy of the snippet's blocks, with new keys.
- The picker for a Snippet field offers only snippets whose declared context the entry provides ([Slots](slots.md#snippets-and-their-context)).

## Sources

A list field ([Content model](content-model.md#fields)) has a **Filled by** select: Hand-picked, or one of the sources of its kind that fit the context ([Sources](sources.md)).

- A source's parameter fields are configured inline, under the select.
- The number of items to show is set beside it, and becomes the page size when the list paginates.
- The field shows how many items the source yields right now, in the preview's channel, locale, Shown to and Date.

## Bindings

- **A ⚡ button** beside a bindable field lists the bindable properties of the context the entry provides or the snippet declares, plus the channel and the customer ([Slots](slots.md#bindings)). Only properties whose type fits the field are listed.
- **A bound field** shows a chip with the property's label, and an optional fallback for an empty value.
- **Rich text** has a **Binding** button in its toolbar for the same list. It inserts a token, which also takes a fallback.
- Bound values and tokens render with real values in the preview, from the sample object, the preview channel and the Shown to customer group. A customer binding in preview has no customer, so it shows its fallback.

## Snippet preview

- **The preview is the real host page** of one of the snippet's placements, with the draft snippet in its slot. The slot is outlined and labelled.
- **The placement picker** switches between the snippet's placements. Placements themselves are read-only in the builder and managed on the entry's overview page ([Admin](admin.md#entry-overview-page)).
- **The sample object picker** chooses the host-page object, such as a product. It defaults to an object that matches the placement's page scope, and marks the objects that match. A warning shows when the chosen object is outside the scope.
- **A snippet with no placements** previews on a plain page with a sample object of its declared context.
- **A snippet that loses its capped slot** for the current Shown to still shows there in the preview, and the top bar says which snippet such shoppers actually see ([Targeting](targeting.md)).

## Translation aids

- The locale switcher shows untranslated and outdated counts per locale.
- **Shared fields** carry an "All languages" badge, so editors know an edit changes every locale.
- **An empty translatable field** shows its fallback value as a placeholder, with **Copy it here**.
- **An outdated field** shows the current source text, with **Mark as up to date**.
- **A banner** shows when the entry has no translation in the current locale, with a way to add one. The builder offers a translation for each locale of the entry's channels.

See [Localization](localization.md) for the rules behind these.

## Saving

The builder holds the entry's working copy as JSON in the browser and saves it a moment after each change. There's no Save button. Undo works in the browser and is saved like any other change.

- **What's saved.** The builder sends the changes it made since it last loaded: values by field and locale, and blocks added, moved or removed. For a never-published entry they're written to its rows, and otherwise to its draft ([Publishing](publishing.md)).
- **Merging.** When a colleague's save doesn't touch the same values, the server applies both and the builder pulls in the colleague's changes. Only a real clash reaches the editor: the same field in the same locale, or an edit to a block someone else deleted. The editor keeps theirs or takes the other.
- **Invalid content saves.** Publish validates and points at the blocks with errors.
- **A scheduled publish locks the draft.** The builder opens read-only, with a way to cancel the schedule.
- **On-save settings** are saved separately and take effect at once.

## Technology

- **Stack.** React and TypeScript, built with Vite. Rich text uses Tiptap 3, with only its MIT core and MIT extensions: no Pro extensions and no Tiptap Cloud. The rich text schema is in [Content model](content-model.md#rich-text).
- **Look.** Its own full-screen layout and CSS, with no Semantic UI or jQuery.
- **Assets.** The plugin ships the builder prebuilt. The compiled assets are committed to the repository, CI checks they match the sources, and `assets:install` installs them. Host projects need no Node, Encore or Stimulus, and v2 adds nothing to the admin's Encore build.
- **Widgets for projects' own field types** are custom elements, loaded from `setono_sylius_cms.builder.scripts` ([Extending](extending.md#widgets)).
- **UI strings** live in the plugin's Symfony translation files and reach the builder as JSON in the admin user's locale. There's no separate JS i18n library.
- **Tests.** Vitest covers the app's TypeScript, such as tree operations, undo and forms rendered from field schemas ([README](README.md#testing)).

## Preview mode

Preview mode renders the working copy on the real shop page, with the same Twig templates shoppers see ([Rendering](rendering.md)).

- **Turning it on.** A signed, short-lived token in the query parameter `_cms_preview` on the shop URL turns it on. It's read from the main request, so slots rendered in Sylius sub-requests preview too ([Rendering](rendering.md#preview-mode-and-share-links)). The builder gets the token from its JSON endpoints and loads the iframe from the admin's own host, so the preview works when channels have their own hostnames.
- **What the token forces:** the channel, the locale, the sample object (the host-page context), Shown to and Date. Every condition on the page follows Shown to and Date, including those on other snippets in slots. The forced Date applies to date windows only, not to other entries' scheduled publishes.
- **The edited entry** renders its working copy, ignoring its own audience, date window, `enabled` switch and publish state. Its blocks still follow the pickers. Everything else on the page shows its published content.
- **Starting values.** Shown to starts as Guest if the entry's audience allows guests, and otherwise as the first group it allows. Date starts as now, or as the start of the entry's date window when that's in the future.
- **Responses** send `Cache-Control: no-store` and `X-Robots-Tag: noindex`.
- **Templates can tell.** `cms_preview_mode()` returns `builder` in the preview, so themes leave out analytics and other tracking, and the iframe's reloads aren't counted as page views ([Extending](extending.md#twig)).

### The bridge

Preview mode injects a bridge script into the page, which shoppers never get.

- It draws the overlays, and handles click-to-select, + between blocks, the selected block's toolbar and typing in place.
- It talks to the builder only by `postMessage`.
- **Markers.** In preview mode the plugin brackets each block's output with HTML comment markers carrying its key, and `cms_field()` marks fields that can be typed in place. Block templates need no code specific to preview mode. The marker format and the `postMessage` protocol are internal, not part of the BC promise.
- **The `cms:render` event.** After the bridge patches a block into the page, it dispatches a bubbling `cms:render` `CustomEvent` from the block's root element, with `detail: {key, type}`. Block types with storefront JavaScript, such as carousels, initialise on it as well as on page load. This event is a public contract.

### Updating the preview

- **On a change**, the builder asks the server to re-render only the changed blocks, through the same rendering pipeline as the shop. The bridge morphs them into the page and dispatches `cms:render` for each.
- **The whole iframe reloads** when:
  - the locale, the channel, the sample object, the placement, Shown to or Date switches
  - an entry field that the layout shows changes ([Layouts and presets](layouts-and-presets.md))
  - the layout or a layout field changes
- **A block that throws while rendering** shows its error on that block in the preview, in dev and in production alike, and is logged. The rest of the preview still renders ([Rendering](rendering.md#failures)).

## JSON endpoints

The builder talks to plain JSON controllers under the admin firewall, not to API Platform. Every endpoint below is prefixed with `/{admin}/cms/api`, where `{admin}` is Sylius's admin path ([Routes](extending.md#routes)). Each requires an admin user and checks a CSRF token on every request that changes something.

**These endpoints are internal.** Only the builder app that ships with the plugin calls them, so their paths, route names and bodies aren't part of the BC promise and can change in any release, together with the app. They're listed here to guide the build. Two things they carry are contracts anyway: entries travel in the [entry format](content-model.md#entry-format), and what widgets reach through `.builder` ([Extending](extending.md#widgets)) keeps its shape.

| Name (`setono_sylius_cms_admin_api_…`) | Method | Path | Purpose |
| --- | --- | --- | --- |
| `registry` | GET | `/registry` | Block types, layouts, content types, their field schemas, block styles, sources, condition kinds, bindable properties, link targets, slots, context keys, the effective upload limit ([Media](media.md#what-the-library-holds)) and the UI strings, in the admin user's locale. |
| `entry_show` | GET | `/entries/{uuid}` | The working copy: the draft, or the rows of a never-published entry, in the entry format. Also its on-save settings, its status, its lock state, and who else has it open. |
| `entry_save` | PATCH | `/entries/{uuid}` | Autosaves a change set since the last load. Answers with the merged changes from colleagues, or the clashes. |
| `entry_settings` | PUT | `/entries/{uuid}/settings` | Saves the on-save settings: enabled, date window, channels, audience, "Homepage of" and position. |
| `entry_validate` | POST | `/entries/{uuid}/validate` | Validates the working copy as publish would, and lists errors by block key and field. |
| `entry_publish` | POST | `/entries/{uuid}/publish` | Publishes now. Answers with validation errors or a `BeforeEntryPublish` veto when it refuses. |
| `entry_schedule` | POST | `/entries/{uuid}/schedule` | Schedules a publish at a time. Validates first, as publishing does. |
| `entry_schedule_cancel` | DELETE | `/entries/{uuid}/schedule` | Cancels the scheduled publish and unlocks the draft. |
| `entry_discard` | POST | `/entries/{uuid}/discard` | Discards the draft. |
| `entry_presence` | POST | `/entries/{uuid}/presence` | Tells the server the editor still has the entry open, and answers with who else does. |
| `preview_token` | POST | `/preview/token` | Creates a preview token for an entry, channel, locale, sample object, placement, Shown to and Date, and answers with the iframe URL. |
| `preview_render` | POST | `/preview/render` | Renders the given blocks of the working copy under a preview token, and answers with their HTML by key. |
| `search` | GET | `/search/{kind}?q=` | Search for pickers. `{kind}` is `product`, `taxon`, `entry`, `snippet`, `media`, `customer_group`, `product_attribute` or a link target's code. |
| `source_count` | POST | `/sources/count` | How many items a source yields for given parameters under a preview token. |
| `media_upload` | POST | `/media` | Uploads files into a folder, answering with the new media items or the duplicates found ([Media](media.md)). |
| `preset_index` | GET | `/presets` | The code and editor presets, with their metadata and whether each fits. |
| `preset_create` | POST | `/presets` | Saves the selected blocks, or the whole tree as a starting point, as an editor preset. |
| `preset_replace` | PUT | `/presets/{uuid}` | Replaces an editor preset's blocks ("Replace <name>"). |
| `preset_update` | PATCH | `/presets/{uuid}` | Renames or recategorises an editor preset. |
| `preset_delete` | DELETE | `/presets/{uuid}` | Deletes an editor preset. |
| `preset_insert` | POST | `/presets/{uuid}/insert` | Returns a preset's blocks ready to insert into an entry: upgraded, with new keys, translation keys resolved for the entry's locales, and the blocks of removed types left out. |
| `revision_index` | GET | `/entries/{uuid}/revisions` | The entry's revisions, with who published each and when. |
| `revision_preview` | POST | `/revisions/{id}/preview` | Creates a read-only preview token for a revision. |
| `revision_restore` | POST | `/revisions/{id}/restore` | Copies a revision into the draft. |
| `share_link_index` | GET | `/entries/{uuid}/share-links` | The entry's share links. |
| `share_link_create` | POST | `/entries/{uuid}/share-links` | Creates a share link for a channel and locale, and for a snippet a placement and sample object. Shown to and Date are fixed now. |
| `share_link_revoke` | DELETE | `/share-links/{id}` | Revokes a share link. |
