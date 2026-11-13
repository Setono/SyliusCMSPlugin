# How leading CMSs handle visual editing, publishing and media

Research for [#188](https://github.com/Setono/SyliusCMSPlugin/issues/188), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186).
Researched 2026-11-13 from official docs, changelogs and first-hand GitHub issues and forums. Every claim is followed by its source. Lines marked **(inference)** are my reading, not the source's words.

Tools surveyed: Shopify theme editor, Storyblok Visual Editor, Builder.io, Payload Live Preview, Sanity Presentation tool, WordPress Site Editor, Webflow (Designer vs Editor / Edit mode).

## Answer in brief

The best editors all use the same shell: the real storefront in an iframe beside a structured form. You click an element in the preview to jump to its form, and an outline or tree shows the nesting. Truly inline editing is limited to plain text. Publishing has three tiers: save a draft, schedule one document, and schedule a bundle of changes as a "release". Every tool pairs this with a version list you can restore from. Targeting is rarely a separate rules screen. The most usable form is a context switcher in the editor (pick a market and edit its overrides, with reset to default) plus a start/end window on a piece of content. Media libraries are converging on folders plus metadata, a focal point stored on the asset, per-locale alt text and a "used in" view.

---

## 1. Visual builder interaction

| Tool | Preview | Editing surface | Nesting shown as | Drag and drop |
|---|---|---|---|---|
| Shopify theme editor | Live storefront preview | Sidebar: structure tree + settings panel (two panels at ≥1600px) | Sidebar tree; theme blocks nest up to 8 levels | Drag handle in sidebar; preview inspector to reorder/hide/duplicate |
| Storyblok | Site in iframe | Right-hand editing sidebar; "Form" view as alternative | "Layers" outline + hover context menus in preview | Rearrange supported; method not documented on the manual page |
| Builder.io | Canvas is the page | Canvas + Style/Options tabs | "Layers" tab | Drag on canvas and in Layers, blue drop indicator |
| Payload | Frontend in iframe in the admin | Normal field form (no click-to-edit in core) | Form's nested blocks/arrays | Form only |
| Sanity | Frontend in iframe (Presentation tool) | Document form beside preview | Form + overlays | Drag array items in the preview |
| WordPress Site Editor | Canvas is the page (block theme) | Inline on canvas + Settings sidebar | "List View" tree, Zoom out view | Canvas and List View |
| Webflow | Canvas is the page | Designer (full) vs Edit mode / Content editor (content only) | "Navigator" | Designer only |

**Shopify.**
- The sidebar lists the page structure. Clicking a section loads it in the preview and opens its settings, and on a wide screen two panels show structure and settings side by side. Sections reorder by a drag handle. Blocks reorder within a section and can move to compatible sections, but not into an incompatible or full one. Required blocks can be hidden but not moved or deleted. Limit: 25 sections and 1,250 blocks per template. Theme blocks nest up to eight levels, while section blocks cannot nest. ([Sections and blocks](https://help.shopify.com/en/manual/online-store/themes/theme-structure/sections-and-blocks), [Blocks](https://shopify.dev/docs/storefronts/themes/architecture/blocks))
- The "Preview inspector" lets merchants click sections and blocks in the preview to reach their settings, and reorder, hide or duplicate them from there. It is on by default in free themes. ([Preview inspector](https://help.shopify.com/en/manual/online-store/themes/customizing-themes/theme-editor/preview-inspector))
- Live preview without a reload works only for some settings. Colors and text settings (`text`, `textarea`, `inline_richtext`, `richtext`) update live if the theme follows rules: no filters except `escape`, and the text must be the sole child of its element. Otherwise the preview re-renders the section. The theme can detect the editor with `request.design_mode` / `Shopify.designMode`, but Shopify says not to change the preview with it ("merchants should see what customers see"). ([Theme editor for developers](https://shopify.dev/docs/storefronts/themes/tools/online-editor))
- Merchants can add or edit products and collections without leaving the editor. ([Theme editor](https://help.shopify.com/en/manual/online-store/themes/customizing-themes/theme-editor))

**Storyblok.**
- The page loads in an iframe with the editing sidebar on the right. A left toolbar has "Layers" (an outline of the blocks in the story), "Content" and "Blocks". There are device toggles for "Desktop", "Mobile" and "Full-width". You add a block by hovering a blocks field to reveal a plus icon and picking from a popover. ([Visual Editor manual](https://www.storyblok.com/docs/manuals/visual-editor))
- The link is two-way. Clicking an element in the preview opens its block in the editor, and clicking a block in the editor scrolls the preview to it. Outlines and context menus appear around blocks in the preview. It works through the `@storyblok/preview-bridge` script and an `_editable` property per block, rendered as `data-blok-c` / `data-blok-uid` attributes. ([Visual Editor concept](https://www.storyblok.com/docs/concepts/visual-editor))
- Field-level comments ("Start a discussion") support @mentions. ([Visual Editor manual](https://www.storyblok.com/docs/manuals/visual-editor))

**Builder.io.**
- The "Layers" tab shows the hierarchy. Dragging a layer reorders it or nests it in a container, with a blue highlight for the drop target. Layers can be renamed, grouped, locked and deleted. ([Layers tab](https://www.builder.io/c/docs/layers-tab), [Intro to the Visual Editor](https://www.builder.io/c/docs/101-visual-editor))
- Builder is closer to a design tool. Editors work on a free-form canvas with Style tabs, not on predefined section slots. **(inference)** This gives the most freedom and also the biggest risk of off-brand pages.

**Payload.**
- Live Preview loads the frontend in an iframe in the admin. Changes are sent by `window.postMessage` "as you type", with no save needed. Breakpoints are configurable, and the preview can pop out into its own window. The `admin.livePreview.url` function receives unsaved `data` and `locale`, and returning `null` disables preview for that document. ([Live Preview](https://payloadcms.com/docs/live-preview/overview))
- Core has no click-to-preview selection. A 2026 community plugin adds "clicking a block in the preview selects its row in the form", and commenters there confirm form/preview sync isn't in core. The "Live block editor" request dates from 2022. ([Discussion #16561](https://github.com/payloadcms/payload/discussions/16561), [Discussion #997](https://github.com/payloadcms/payload/discussions/997))

**Sanity.**
- The Presentation tool shows the frontend in an iframe. Click-to-edit overlays jump to the exact field in the Studio form, and editors can drag to reorder array items (sections) in the preview. A "Used on" panel (`defineLocations`) lists the pages a document appears on and updates as fields change. ([Visual editing intro](https://www.sanity.io/docs/visual-editing/introduction-to-visual-editing), [Configuring Presentation](https://www.sanity.io/docs/visual-editing/configuring-the-presentation-tool))
- The cost is "stega". Click-to-edit works by hiding invisible characters in strings, which breaks string comparisons in frontend code (`imageLocation === 'left'`). Stripping the characters breaks the overlays, and developers call this "forces defensive coding". ([sanity#9303](https://github.com/sanity-io/sanity/issues/9303), [client#1127](https://github.com/sanity-io/client/issues/1127))

**WordPress Site Editor.**
- You edit inline on the canvas. The toolbar has the block inserter, Undo/Redo, "List View" (a tree for nested structures, multi-select, move and group), Preview by viewport, a Settings sidebar and Styles. "Zoom out" gives a condensed view of the page's sections. Saving opens a list of every entity changed (templates, template parts, synced patterns, menus), and you choose which to save. ([Site Editor](https://wordpress.org/documentation/article/site-editor/))
- The main complaint is mode confusion. Users edit the shared template when they meant to edit the page ("lack of boundaries between template and content makes it easy to edit the template by mistake"). Changing a theme template quietly creates a custom copy. Users can't find their way back to page editing. ([gutenberg#49404](https://github.com/WordPress/gutenberg/issues/49404), [#72509](https://github.com/WordPress/gutenberg/issues/72509), [#27899](https://github.com/WordPress/gutenberg/issues/27899), [#51457](https://github.com/WordPress/gutenberg/issues/51457))

**Webflow.**
- The Designer has full layout and style control. Content editors only edit content: text, images and CMS items. Webflow is retiring the legacy Editor and replacing it with roles inside the Designer. "Content editor" replaces Edit mode and "Marketer" replaces Build mode. ([Seats, roles and permissions](https://help.webflow.com/hc/en-us/articles/33961273067411-Seats-roles-and-permissions-overview), [Legacy Editor deprecation](https://webflow.com/blog/legacy-editor-deprecation), [Content editing roles](https://webflow.com/blog/content-editing-commenter-roles))
- On the forum, clients can't add a layout section without learning the Designer, and one reply suggests a simpler platform for clients who need to add sections. ([Forum: "Build your own" section](https://discourse.webflow.com/t/build-your-own-section-in-page/257300))

**Takeaways for interaction**
- The winning pattern is the real page in an iframe, plus a sidebar form, with two-way selection: preview → form and form → preview. Shopify, Storyblok and Sanity all ship it, and Payload users build it themselves because core lacks it.
- A tree or outline view is the standard answer to nesting: Shopify's sidebar, Storyblok "Layers", Builder "Layers", WordPress "List View", Webflow "Navigator". Shopify caps nesting depth (8) and page size (25 sections).
- Inline editing is narrow everywhere except the page-builder tools (WordPress, Builder, Webflow Designer). Shopify limits live text updates to a few setting types with strict markup rules.
- A layout that is fixed by the designer and only filled by the editor (Webflow) is safe but frustrates clients. A free canvas (Builder) is flexible but risky. Shopify's slots with typed sections and blocks sit in the middle. **(inference)**

## 2. Preview

| Tool | Live preview | Shareable link for unpublished content |
|---|---|---|
| Shopify | Yes, in editor | Yes for unpublished themes, not the live one; links don't expire when new ones are made |
| Storyblok | Yes (bridge) | "Open preview" in a new tab; release-scoped preview tokens via Releases app |
| Builder.io | Yes (canvas) | Not confirmed in this research |
| Payload | Yes, as you type | Not in core (preview URL is developer-built) |
| Sanity | Yes (Presentation) | Yes: Share icon → link + QR code, revocable by toggling |
| WordPress | Preview by viewport / new tab | No in core; "Public Post Preview" plugin; on the Phase 3 roadmap |
| Webflow | Canvas; "Publish to staging" | Staging domain |

- **Shopify:** you can share a preview link for an unpublished theme, optionally choosing country and language. You can't share the published theme. ([Adding themes](https://help.shopify.com/en/manual/online-store/themes/adding-themes))
- **Sanity:** the Share icon in the preview address bar creates a link or QR code for people without Studio access. Toggling sharing off and on invalidates the old link. Sharing a draft exposes other drafts too, and by default only Editors and above can toggle it. ([Preview and page building](https://www.sanity.io/docs/user-guides/preview-and-page-building), [changelog: preview sharing](https://www.sanity.io/docs/changelog/bc5d4656-a959-4cc1-925e-18191cfef100))
- **Storyblok:** previews fetch the draft version with a preview token, validated for one hour. The Releases app adds release-based tokens to preview unpublished bundles. ([Visual Editor concept](https://www.storyblok.com/docs/concepts/visual-editor), [Releases](https://www.storyblok.com/docs/apps/releases))
- **WordPress:** the request for a core "public preview" link was closed, and the workaround is the Public Post Preview plugin. Gutenberg's "Phase 3: Workflows" lists sharing draft links with permission controls. ([gutenberg#21656](https://github.com/WordPress/gutenberg/issues/21656), [#61160](https://github.com/WordPress/gutenberg/issues/61160))
- **Webflow:** a single page can go to staging first ("Publish to staging"). ([Publish a single page](https://help.webflow.com/hc/en-us/articles/50064706725395-Publish-a-single-page))

**Takeaway:** a share link is expected. The good version (Sanity) is revocable, works without an account and shows a chosen context (Shopify adds country/language). Its known risk is that one link exposes all drafts.

## 3. Publishing: drafts, scheduling, revisions

| Tool | Drafts | Scheduled publish | Scheduled unpublish | Bundles | Revisions / rollback |
|---|---|---|---|---|---|
| Shopify | Unpublished theme copies; Save on live theme is live | Not native (not found) | — | Whole theme | "Version history" (Canvas themes) |
| Storyblok | Save vs Publish; workflow stages | Per story ("Schedule publishing") | — | Releases app (calendar) | Version on every save/stage change; Restore |
| Builder.io | Draft/publish | Start + end date (Pro); recurring for Sections/Symbols | Yes (end date) | — | History: All / Publishes / Checkpoints; revert |
| Payload | `_status` draft/published, autosave | `schedulePublish` (needs jobs runner) | Yes | — | Versions with diff + restore |
| Sanity | Draft overlay on published | "Scheduled drafts" (paid plans) | — | Content Releases (Enterprise add-on) | History panel, "Revert to revision", revert release |
| WordPress | Draft / pending | Core (future publish date) | Not core | — | Post revisions; style revisions |
| Webflow | Draft pages; staging | CMS items only, never-published items only | — | Site publish | Site backups |

- **Storyblok:** the default workflow has three stages: Drafting → Reviewing → Ready to Publish. Only the last can publish, and custom workflows are allowed. "Schedule publishing" sits under "More options" next to Publish. A new version is made on every save or stage change, and "Restore" replaces the current story. ([Workflows](https://www.storyblok.com/docs/manuals/workflows), [Visual Editor manual](https://www.storyblok.com/docs/manuals/visual-editor), [History](https://www.storyblok.com/docs/manuals/history))
- **Builder.io:** scheduling (Pro plan) sets start and end dates from the Schedule icon. When the end date passes the entry is unpublished, so Builder advises keeping a fallback entry. Sections and Symbols can have recurring schedules. History has "All", "Publishes" and "Checkpoints" (manual snapshots with Cmd/Ctrl+S). Retention runs from 7 days (Free) to unlimited (Enterprise). A forum answer says there is no visual diff between versions. ([Scheduling](https://www.builder.io/c/docs/scheduling), [History](https://www.builder.io/c/docs/history), [Forum](https://forum.builder.io/t/version-related-query-in-builder-io/3712))
- **Payload:** status is Draft, Published or "Changed" (published, with a newer draft). `autosave` is available. `schedulePublish: true` schedules publish and unpublish, but runs as background Jobs, so without a job runner it "will never be executed". There is per-locale status (`localizeStatus`, beta). There are versions with diffs and restore, but restoring a draft version once unpublished the live document (bug report). Uploaded files are not versioned. ([Drafts](https://payloadcms.com/docs/versions/drafts), [Versions](https://payloadcms.com/docs/versions/overview), [payload#18389](https://github.com/payloadcms/payload/issues/18389))
- **Sanity:** Content Releases group changes so they are previewed, validated, scheduled and published together, and a scheduled release locks its documents until unscheduled. "Scheduled drafts" schedule a single document. Since 2025-11-06 Releases is an Enterprise add-on, while Scheduled Drafts are on all paid plans. History has "Revert to revision", and reverting a release is possible. ([Content Releases](https://www.sanity.io/docs/user-guides/content-releases), [blog](https://www.sanity.io/blog/introducing-content-releases), [operators guide](https://www.sanity.io/docs/user-guides/content-operations-cheatsheet))
- **WordPress:** a future date turns "Publish" into "Schedule". A scheduled post goes live on the first visit after its time (WP-cron), so it can be late. Global Styles have their own revisions. ([Schedule a Post](https://make.wordpress.org/support/user-manual/posts/schedule-a-post/), [Site Editor](https://wordpress.org/documentation/article/site-editor/))
- **Webflow:** CMS item scheduling works only for items never published before, uses the site time zone, and isn't on Starter/Basic. Single-page publish can't be scheduled or unpublish a page. ([Scheduled publishing](https://help.webflow.com/hc/en-us/articles/33961272476563-Scheduled-publishing), [Publish a single page](https://help.webflow.com/hc/en-us/articles/50064706725395-Publish-a-single-page))
- **Shopify:** the editor recommends duplicating your theme before customizing, so you can start over. Canvas themes have a "Version history" dialog, where restoring changes the working copy and customers see it only after publish. ([Theme editor](https://help.shopify.com/en/manual/online-store/themes/customizing-themes/theme-editor), [search summary of Canvas docs](https://help.shopify.com/en/manual/online-store/themes/canvas/customizing)) **(inference)** Shopify editing works on whole themes. To stage a change you copy the entire theme.

**Takeaways for publishing**
- Three tiers recur: **draft / published / changed-since-publish** status, **schedule one item**, and a **bundle of changes ("release") with its own preview and schedule**. Releases are always the premium tier (Sanity Enterprise, Storyblok app, Builder Pro for scheduling).
- Scheduled **unpublish** (Builder end date, Payload) needs a fallback for what shows afterwards. Builder says so explicitly.
- Scheduling runs on background jobs. Payload's "never executed" without a runner and WordPress's late cron show the failure mode: the editor sees "scheduled" while nothing is running. **(inference)** That matters for Sylius, which has no built-in scheduler.
- Rollback that people trust has clear version labels (Builder's Publishes vs Checkpoints), a diff (Payload, Sanity) and restore-to-draft, not restore-to-live (Payload's bug report shows the latter surprises users).

## 4. Targeting (audience, schedule, market)

- **Shopify:** context is a **switcher**, not a rules screen. Pick a market in the "Market" drop-down at the top of the editor and edit. Changes become overrides of the store default. Added sections trigger an "Overriding section order" confirmation. A reset icon works per setting, per section/block or per whole template. Defaults flow to markets that haven't overridden the order. Global settings such as colors and typography can't vary by market. Per-market theme customization is plan-limited (Advanced/Plus per the help page). ([Create and manage overrides](https://help.shopify.com/en/manual/online-store/themes/customizing-themes/store-contextualization/create-and-manage-overrides), [Customizing themes for markets](https://help.shopify.com/en/manual/online-store/themes/customizing-themes-for-markets))
- **Builder.io:** a "Targeting" icon in the editor offers built-in conditions (Device, URL path) and custom attributes (for example customer segment). Several entries can share a URL, and the **first matching entry in list order wins**, with priority set by dragging the list. "Variant Containers" target or schedule a single block, such as a banner, without touching the rest of the page. ([Targeting](https://www.builder.io/c/docs/targeting), [Custom targeting attributes](https://www.builder.io/c/docs/custom-targeting-attributes), [Variant Containers](https://www.builder.io/c/docs/variant-containers))
- **Storyblok, Payload, Sanity, WordPress:** I found no native audience targeting. Time-based targeting comes from scheduling and releases. Locale comes from field- or document-level translation. **(inference from absence; not exhaustively verified)**
- **Webflow:** localization lets a secondary locale override an asset, alt text or content, with "reset to inherit" from the primary. ([Localize content and styles](https://help.webflow.com/hc/en-us/articles/33961240676371-Localize-content-and-styles)) I did not verify Webflow Optimize (personalization).

**Takeaway:** the two usable forms are (a) **edit in context**: pick channel/market/locale and edit overrides of a default, with visible reset (Shopify, Webflow locales), and (b) **variants of a block or page with conditions and a first-match order** (Builder). Both put targeting next to the content, not on a separate rules admin.

## 5. Media library

| Tool | Folders vs tags | Alt text per locale | Focal point | Variants | Usage tracking |
|---|---|---|---|---|---|
| Shopify Files | No folders (saved "Views" are filters) | Not verified | Yes, on Files page or theme image picker | Theme `image_tag` / CDN | "Used in" column + filter |
| Storyblok | Folders **and** tags | Yes (translatable metadata) | Yes (JPEG/PNG) | Image service | Not found |
| Payload | `folders` per upload collection | Via localized fields (not shown in docs) | `focalPoint` + `crop` | `imageSizes` (Sharp) | Not found |
| Sanity Media Library | Nested folders + collections; "aspects" as metadata | Field-level i18n (not verified for alt) | Hotspot + crop stored on the *usage*, not the asset | URL builder | References dialog incl. drafts and releases; delete blocked while referenced |
| WordPress | No folders in core (wontfix / open tickets) | No core i18n | Cover/Image block only | Registered sizes + automatic `srcset` | No |
| Webflow | Folders in Assets panel | Yes: asset-level per locale, plus per-instance override | — | — | — |

- **Shopify Files:** filter by "Used in" and "Alt text", and set the focal point on the Files page or in the theme image picker. Themes must render with `image_tag` to respect it. ([Files](https://help.shopify.com/en/manual/shopify-admin/productivity-tools/file-uploads), [Input settings](https://shopify.dev/docs/storefronts/themes/architecture/settings/input-settings), [Unified media library changelog](https://changelog.shopify.com/posts/unified-media-library-pools)) Merchants keep asking for folders. A 2026 reply says "Views" are saved filters, not folders, and apps fill the gap. ([Community thread](https://community.shopify.com/t/how-can-i-effectively-organize-my-files-in-shopify/273035/57))
- **Storyblok:** both tags and folders, where tags "organize assets across folders". The Overview window edits title, alt, copyright and custom fields. Alt text can be made **required** (Settings → Asset Library). The focal point is a click on the image. Custom metadata fields can be translated and marked required. ([Assets manual](https://www.storyblok.com/docs/manuals/assets), [Translatable asset metadata](https://www.storyblok.com/mp/custom-metadata-for-assets))
- **Payload:** `imageSizes` resizes automatically on upload. The `crop` tool is on by default. The `focalPoint` selector shows when sizes are defined. `bulkUpload` is available, and `folders: true` is set per collection. A bug report says bulk upload ignores the current folder. ([Uploads](https://payloadcms.com/docs/upload/overview), [Collection config](https://payloadcms.com/docs/configuration/collections), [payload#16287](https://github.com/payloadcms/payload/issues/16287))
- **Sanity:** "hotspot and crop" are stored on the image *field* in each document, separate from the asset. **(inference)** That gives a different crop per placement, with no single crop for the whole asset. The Media Library has nested folders, developer-defined "aspects" (typed metadata, used for tagging) and usage tracking that covers drafts and releases. It refuses to delete an asset that is still referenced. ([Image type](https://www.sanity.io/docs/studio/image-type), [Media Library intro](https://www.sanity.io/docs/media-library/introduction), [Aspects](https://www.sanity.io/docs/media-library/create-aspect), [Changelog: in-use references](https://www.sanity.io/docs/changelog/apiChange-ml-thirty-memes-fold), [Limits](https://www.sanity.io/docs/media-library/limits-and-usage))
- **WordPress:** the folders ticket was closed wontfix (#23594). The open ticket (#47839) suggests virtual folders through hierarchical taxonomies. A 2026 Gutenberg issue proposes folders for WP 7.2+. Searching by alt text is still an open enhancement. Images get automatic `srcset` from registered sizes. Focal points exist only per block (Cover, Image). ([trac#23594](https://core.trac.wordpress.org/ticket/23594), [trac#47839](https://core.trac.wordpress.org/ticket/47839), [gutenberg#79779](https://github.com/WordPress/gutenberg/issues/79779), [trac#39004](https://core.trac.wordpress.org/ticket/39004), [Responsive Images](https://developer.wordpress.org/apis/responsive-images/), [Cover block](https://wordpress.org/documentation/article/cover-block/))
- **Webflow:** alt text per locale is set once per asset (Assets panel cog) or per instance (Element settings), with reset to inherit from the primary locale. Secondary locales can swap the image itself. ([Localize content and styles](https://help.webflow.com/hc/en-us/articles/33961240676371-Localize-content-and-styles), [Image](https://help.webflow.com/hc/en-us/articles/33961290329235-Image))

**Takeaways for media**
- Folders are what users ask for, and their absence is the top complaint (Shopify, WordPress). Tags or typed metadata (Storyblok, Sanity aspects) complement folders and don't replace them.
- Alt text needs two levels: a default on the asset, overridable where it's used, and per locale (Webflow, Storyblok). Making it required is a cheap win (Storyblok).
- Focal point on the asset plus generated size variants (Shopify, Storyblok, Payload) is the common baseline. Sanity's per-usage crop is more flexible but more work.
- "Used in" is now standard (Shopify, Sanity), and Sanity uses it to block deleting referenced assets.

## 6. What users praise and criticise

| Pattern | Praised / easy | Criticised / hard |
|---|---|---|
| Two-way preview ↔ form selection | Storyblok, Sanity, Shopify inspector | Payload users build it themselves; Sanity's stega side effects |
| Clear content vs layout boundary | Webflow content role, Shopify typed sections | WordPress template-vs-page confusion (many issues) |
| Constrained layout | Safe, on-brand (Webflow, Shopify) | Clients can't add sections without the Designer (Webflow forum) |
| Multi-entity save | WordPress lists every changed entity | Global changes can land unintentionally (#49404) |
| Scheduling | Builder start/end + fallback | Silent failure without a job runner (Payload), late cron (WordPress) |
| Releases | Sanity, Storyblok bundle + preview | Premium-only; scheduled release locks documents |
| Media organisation | Storyblok folders + tags, Sanity folders + usage | No folders in Shopify Files or WordPress core |

## 7. Implications for Sylius CMS v2 (inference)

- Build the editor as the real Sylius shop page in an iframe with a sidebar form and a tree. Do two-way selection by marking each rendered block with data attributes in edit mode (Storyblok's `data-blok-uid` approach), not by mixing invisible characters into strings (Sanity's stega).
- Keep live text editing to plain text fields. Re-render a block's Twig server-side for everything else, as Shopify does per section.
- Show **which scope** an edit affects: page versus shared slot or layout. WordPress's template confusion is the anti-pattern.
- Ship draft / published / changed status, a per-item schedule with start **and** end, versions with restore-to-draft, and a revocable share link. Leave releases for later. Make scheduling depend visibly on a configured worker or cron, and warn when none has run.
- Present targeting as a context switcher (channel, locale) with overrides of a default and visible reset. Customer group and date window go on a block or page variant with first-match order.
- Media: folders plus tags, a focal point on the asset, required alt text per locale (asset default with per-use override), generated variants (LiipImagine is already in Sylius), and "used in" with delete protection.

## Gaps and caveats

- No primary source found for: Builder.io share links for unpublished content and its asset library; Storyblok asset usage tracking; Shopify scheduled theme publishing; Webflow Optimize; native audience targeting in Storyblok, Payload or Sanity.
- Several Shopify help pages are years old, and Shopify "Canvas" themes appear to be a newer editor. The Canvas version-history detail comes from a search summary of Shopify's Canvas docs, not a direct read.
- Plan and pricing tiers change often (Sanity Releases moved to an Enterprise add-on in Nov 2025).
- User sentiment comes from first-hand GitHub issues and official forums, which over-represent developers and power users.
