# How leading CMSs model content

Research for [#187](https://github.com/Setono/SyliusCMSPlugin/issues/187), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186). Researched 2026-11-13 against official docs, official repos and vendor changelogs. Every claim links to the page or file it came from. Where a source could not be reached directly, this is noted next to the claim.

## Question

How do leading CMSs structure content, and how do they balance flexibility for developers against ease of use for editors? For each system: content primitives, who defines schemas and where, reusable presets/templates/patterns, localization model and fallbacks, and how content and schema move between environments.

## Answer in brief

Every system surveyed has the same core: a **typed, ordered tree of blocks**, stored as structured data (usually JSON) inside a document, with block types declared by a developer. They differ in four ways:

1. **Where the schema lives.** It is code in Shopify, Sanity, Payload, Wagtail, Sulu and Gutenberg. It is admin UI in Storyblok and Contentful. Craft and Statamic edit it in the UI but save it as files in the repo.
2. **How much nesting editors get, and the guardrails around it.**
3. **Whether reuse copies content or references it.** Presets copy; synced patterns and snippets reference.
4. **Whether translation is per field or per document.**

The systems that serve developers and editors best keep the schema in versioned code. They give editors guardrails (allowed children, limits, presets with preview images) and both kinds of reuse. They share layout across locales and translate leaf fields with a defined fallback.

## Comparison at a glance

| CMS | Content primitives | Schema defined in | Reuse | Localization | Environments |
|---|---|---|---|---|---|
| Shopify OS 2.0 | JSON templates → sections → blocks (theme blocks nest; section blocks one level); section groups; app blocks; metaobjects | Code: Liquid `{% schema %}`, theme files | Section/block **presets** (copy); section groups (shared header/footer) | Per-field translations keyed by content digest; default language fallback | Theme is files; theme-editor edits are committed back to Git |
| Storyblok | Stories; content-type / nestable / universal blocks; Blocks field; References field | Admin UI (Block Library); CLI push/pull, TypeScript schema | Block presets (copy) | Field-level, folder-level or space-level; `fallback_lang` | CLI / Management API sync between spaces |
| Sanity | Documents, objects, arrays (page builder), Portable Text, references | Code (Studio config); can be deployed to the dataset | References to documents; insert-menu groups | Field-level (internationalized array) or document-level (linked docs); fallback in GROQ | Datasets per environment; scripted migrations; schemaless store |
| Contentful | Content types, entries, assets; Reference and Rich Text fields; Studio components/patterns | Admin UI or migration scripts; Studio components registered in code | References; Studio patterns | Per-field `localized` flag; locale fallback chain | Environments cloned from master; alias swap; migrations |
| Payload | Collections, globals, fields; Blocks field; blocks in Lexical rich text | Code (TypeScript config) | `blockReferences` (define once, use in many fields) | Field-level `localized`; per-locale `fallbackLocale` | Config in Git; generated SQL migrations |
| Wagtail | Page models; StreamField (StreamBlock/StructBlock/ListBlock); snippets | Code (Python models) | Snippets via `SnippetChooserBlock` | Document-level (`translation_key` + locale, a tree per locale) | Migrations for schema; `wagtail-transfer` for content |
| Craft CMS 5 | Sections, global entry types, fields; Matrix = nested entries | Admin UI, saved as Project Config YAML | Global entry types and fields, multi-instance fields | Per-field translation method; sites and propagation | Project Config in Git; content not included |
| Statamic | Collections, blueprints, fieldsets; Replicator sets; Bard (rich text + sets) | Admin UI or YAML, both stored as files | Fieldsets (import with prefix) | Localized entries inherit from an origin entry | Content is flat files in Git by default (Eloquent driver optional) |
| WordPress Gutenberg | Blocks (`block.json`), InnerBlocks; patterns; template parts; templates | Code (`block.json`, theme files); patterns also in admin | Unsynced patterns (copy); synced patterns with overrides (reference) | None in core (Phase 4 "Multilingual" is still planned) | Theme files in Git; Site Editor edits stored in the DB override them |
| Sulu 3 (Symfony) | Pages/articles/snippets as Doctrine entities; XML templates with properties and blocks | Code (XML templates in `config/templates`) | Snippets and snippet areas; global blocks | Dimensions: an unlocalized row plus localized rows; nested-locale fallback; ghost/shadow pages | Templates in Git; content in DB |

## Per-system findings

### Shopify Online Store 2.0

**Primitives.**

- A JSON template lists `sections` (keyed by ID, each with a type, settings and blocks) and an `order` array. It can also set an optional `layout` and `wrapper`. Limits: "JSON templates can render up to 25 sections, and each section can have up to 50 blocks", and a theme can contain up to 1,000 JSON templates. A section can carry a `disabled` flag that hides it while keeping it editable. ([JSON templates](https://shopify.dev/docs/storefronts/themes/architecture/templates/json-templates))
- There are three kinds of block ([Blocks](https://shopify.dev/docs/storefronts/themes/architecture/blocks)):
  - **Theme blocks** live as files in `/blocks`, can be reused across sections, and can nest children.
  - **Section blocks** are declared inside one section's schema, work only in that section, and nest one level only.
  - **App blocks** come from installed apps.
- A section cannot mix section blocks and theme blocks. A theme can have at most 300 theme blocks. Static blocks sit fixed in code: merchants can hide them but not delete them.
- **Section groups** (`header`, `footer`, `aside`, `custom.<name>`) are JSON files of sections that a layout renders with `{% sections 'header-group' %}`. They have the same limits (25 sections, 50 blocks). Merchants can create contextual overrides per market or B2B context, stored as `header-group.context.<string>.json`. ([Section groups](https://shopify.dev/docs/storefronts/themes/architecture/section-groups))
- **App blocks.** A section or theme block opts in with `"type": "@app"`. App blocks are only supported in sections used by JSON templates. A section that accepts app blocks "should have at most one resource setting of each type (for example, one product setting), to avoid ambiguity with autofill". This is how an app block picks up the host page's product. ([App blocks](https://shopify.dev/docs/storefronts/themes/architecture/blocks/app-blocks))
- **Metaobjects** are definitions plus entries for structured, reusable records. Apps can define them in `shopify.app.toml` or through the Admin API, and merchants can define and manage their own. ([Metaobjects](https://shopify.dev/docs/apps/build/custom-data/metaobjects))

**Schema owner.** Developers. The section and block `{% schema %}` JSON declares `settings`, `blocks`, `max_blocks`, `presets`, `default` and `enabled_on`/`disabled_on` (which templates or groups a section may appear in). ([Section schema](https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema))

**Reuse.** Presets are preconfigured versions of a section, with a `name` and a `category` for the picker. They can pre-fill settings and blocks, for example "a slideshow preset that starts with two slides". Only sections with presets can be added in the theme editor. Sections without presets must be placed in the JSON by a developer and cannot be removed by merchants. ([Section schema](https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema), [JSON templates](https://shopify.dev/docs/storefronts/themes/architecture/templates/json-templates))

**Localization.**

- Theme UI strings live in locale files: `*.json` for the storefront and `*.schema.json` for editor labels, each with one `*.default.json`. ([Locales](https://shopify.dev/docs/storefronts/themes/architecture/locales))
- Merchant content is translated per field. Each `translatableContent` entry has a `key`, `value`, `digest` and `locale`, and `translationsRegister` writes one field's translation against that field's digest. ([Manage translated content](https://shopify.dev/docs/apps/build/markets/manage-translated-content))
- Untranslated fields fall back to the theme's default language. A translation is flagged "Outdated" when the source text changes after it was written. ([Help Center: translate theme](https://help.shopify.com/en/manual/online-store/themes/customizing-themes/language/translate-theme), via search snippet)
- Market-specific content is stored as a theme override in the primary locale, not as a translation. ([Manage translated content](https://shopify.dev/docs/apps/build/markets/manage-translated-content))

**Environments.** Content and schema are both theme files. Section order, settings, block data and merchant edits are "all saved in the JSON file within the theme". ([JSON templates](https://shopify.dev/docs/storefronts/themes/architecture/templates/json-templates)) With the GitHub integration, theme-editor changes are committed back to the connected branch by a `shopify` bot, and this cannot be disabled. The recommended setup is a separate branch per unpublished theme. The theme editor warns before overwriting an edit made in Git, but the code editor does not. ([GitHub integration](https://shopify.dev/docs/storefronts/themes/tools/github))

### Storyblok

**Primitives.** Stories are made of blocks, and blocks are made of fields. There are three block categories: "Content type" (a standalone story), "Nestable" (a child block) and "Universal" (both). Blocks nest through the Blocks field. ([Blocks](https://www.storyblok.com/docs/concepts/blocks)) A story's `content` is JSON with `_uid` and `component` keys, and nested blocks sit inline in it, for example in a `body` array. ([Story object](https://www.storyblok.com/docs/api/content-delivery/v2/stories/the-story-object))

The Blocks field can allow or deny specific blocks, block folders or block tags (`restrict_components`, `restrict_type`). The References field links stories and can be filtered by content type (`filter_content_type`) and folder. ([Component schema field object](https://www.storyblok.com/docs/api/management/components/the-component-schema-field-object), [Fields](https://www.storyblok.com/docs/concepts/fields), via search snippets)

**Schema owner.** Mainly the admin UI (the Block Library). The CLI can `components pull` and `components push` schemas as JSON. `schema push` applies a TypeScript definition from `@storyblok/schema`, showing a diff first and supporting rollback. A shared-components app lets child spaces inherit a parent space's schemas. ([Blocks](https://www.storyblok.com/docs/concepts/blocks))

**Reuse.** Admin users create block presets, which are pre-filled blocks. Starring one makes it the default state for new blocks of that type. The Visual Editor shows a draft rendering of the site, and editors pick a block in the preview to edit it. ([Visual Editor](https://www.storyblok.com/docs/manuals/visual-editor))

**Localization.** There are three approaches ([Internationalization](https://www.storyblok.com/docs/concepts/internationalization)):

- **Field-level:** one story holds every language, and each field is marked "Translatable". "All field types except `Blocks` support translations", so the block structure is shared across languages and only leaf fields are translated.
- **Folder-level:** a top-level folder per language or market, so each locale can have its own structure.
- **Space-level:** a separate space per region.

Fallback uses the delivery API parameter `fallback_lang`. The Dimensions app links alternate stories across folders. ([Internationalization](https://www.storyblok.com/docs/concepts/internationalization))

**Environments.** For space-level setups, "The CLI and Management API sync components, stories, and datasources across spaces." ([Internationalization](https://www.storyblok.com/docs/concepts/internationalization))

### Sanity

**Primitives.**

- **Document** types are top-level records. **Object** types are reusable nested structures. **Arrays** hold lists of members, and the **block** type gives Portable Text rich text. **References** link documents, including across datasets. ([Schema types](https://www.sanity.io/docs/studio/schema-types))
- An array "can hold more than one member type, including a mix of references and inline objects". That makes arrays the page-builder primitive.
- Every array item gets a persistent `_key`, so one user can edit an item while another reorders the array.
- `insertMenu` gives the block picker groups and a grid view with `previewImageUrl` thumbnails, falling back to the type icon. ([Array type](https://www.sanity.io/docs/studio/array-type))

**Schema owner.** Code: the `schema.types` list in `sanity.config.js`. ([Schema types](https://www.sanity.io/docs/studio/schema-types)) The Content Lake does not enforce a schema, so studios on older or newer schemas can read the same documents. `sanity schemas deploy` stores a copy of the schema in each dataset for tools that need it. ([Introduction to schemas](https://www.sanity.io/docs/apis-and-sdks/introduction-to-schemas), [Environments](https://www.sanity.io/docs/content-lake/environments), via search snippets)

**Localization.** There are two plugins, and both can be used in one project, chosen per document type. ([Localization](https://www.sanity.io/docs/studio/localization))

- **Field-level** (internationalized-array): "Best for documents that have a mix of language-specific and common fields". All languages publish together.
- **Document-level** (document-internationalization, linked through `translation.metadata` documents): "Best for documents that have unique, language-specific fields and no common content across languages". Each language publishes on its own. This approach is recommended for Portable Text.

Fallback is not built in. You write it in GROQ with `coalesce()`.

**Environments.**

- A dataset per environment. Cloud dataset cloning is limited to some Enterprise plans. Export and import are available, and aliases allow hot-swapping which dataset apps query. ([Datasets](https://www.sanity.io/docs/content-lake/datasets))
- Migrations are scaffolded with `sanity migration create` and dry-run by default. The docs advise testing in a staging dataset and keeping migrations idempotent. ([Schema and content migrations](https://www.sanity.io/docs/content-lake/important-considerations-for-schema-and-content-migrations), via search snippet)

### Contentful

_contentful.com returned HTTP 429 and a bot checkpoint to direct fetches. The claims below come from search-result extracts of the official contentful.com pages linked._

**Primitives.**

- A space holds environments. An environment holds content types, entries and assets.
- A content type can have up to 50 fields. ([Data model](https://www.contentful.com/developers/docs/concepts/data-model/), [changelog: fields-per-CT warning](https://www.contentful.com/developers/changelog/fields-per-ct-warning-checkbox-widget-preview-app-token-ui-extensions-multi/))
- Reference fields link entries or assets, and a validation restricts the allowed content types. ([Available validations](https://www.contentful.com/help/fields/available-validations/)) Rich Text can embed entries.
- **Studio / Experiences** is Contentful's visual builder layer:
  - Developers register their own React components with `defineComponents`. Each definition sets an id, a category and variables, and only the JSON definition is sent to Contentful.
  - Components can accept children.
  - `defineDesignTokens` limits editors to brand values.
  - **Patterns** are saved groups of components.

  ([Register custom components](https://www.contentful.com/developers/docs/experiences/register-custom-components/), [Design tokens](https://www.contentful.com/developers/docs/experiences/design-tokens/), [Nested components](https://www.contentful.com/developers/docs/experiences/nested-components/), [Experiences overview](https://www.contentful.com/developers/docs/experiences/))

**Schema owner.** The admin UI, or migration scripts.

**Localization.** Each field has a `localized` flag. Locales form fallback chains (for example de-CH → de-AT → de-DE), and a chain cannot be cyclic. The Delivery API falls back "only when *no value* is defined for a given locale". An explicit null or empty string is returned as-is and blocks fallback. GraphQL can turn fallback off with `useFallbackLocale: false`. ([Localization with Contentful](https://www.contentful.com/developers/docs/tutorials/general/setting-locales/), [GraphQL locale handling](https://www.contentful.com/developers/docs/references/graphql/locale-handling/))

**Environments.** The recommended flow is:

1. Clone the environment behind the `master` alias.
2. Run the migration script on the clone and test it.
3. Repoint the alias, which takes one API call. Rolling back means pointing the alias back.

The Merge app generates migrations from the differences between environments. Workflows are not copied when an environment is cloned. ([Environments and aliases best practices](https://www.contentful.com/developers/docs/concepts/environments-and-environment-aliases-best-practices/), [Multiple environments](https://www.contentful.com/developers/docs/concepts/multiple-environments/))

### Payload

**Primitives.** Collections and globals are made of fields, and the config is a "code-first", fully typed `payload.config.ts`. ([Config overview](https://payloadcms.com/docs/configuration/overview))

- The **Blocks field** is an array of objects, each with a `slug` (stored as `blockType`) and its own `fields`. Block schemas can nest. ([Blocks field](https://payloadcms.com/docs/fields/blocks))
- **`blockReferences`** defines a block once in the root config and reuses it in many fields. Referenced blocks cannot be changed per collection.
- **`filterOptions`** restricts the allowed blocks based on context, such as sibling data. A saved block that is no longer allowed fails validation on save.
- The Lexical rich-text editor's `BlocksFeature` inserts the same block configs, block-level or inline, into rich text. ([Rich text features](https://payloadcms.com/docs/rich-text/official-features))

**Localization.** It is set per field with `localized: true`, and values are stored keyed by locale. `fallback` defaults to true, and each locale can set its own `fallbackLocale`, one code or an ordered list. A request can override the fallback or disable it.

Localizing a Blocks field gives each locale its own copy of the whole layout. Localizing only child fields shares the layout and translates the leaves. Changing a field to or from localized changes how its data is stored, so it needs a migration. ([Localization](https://payloadcms.com/docs/configuration/localization))

**Environments.** The schema is in Git. For SQL databases, `migrate:create` diffs the config against the database to generate a migration file. In development, Postgres uses push mode instead. No content-transfer tool is documented. ([Migrations](https://payloadcms.com/docs/database/migrations))

### Wagtail

**Primitives.** StreamField stores "an ordered sequence of typed blocks" as JSON on a page model. ([StreamField](https://docs.wagtail.org/en/stable/topics/streamfield.html))

- Blocks are composed from `StructBlock` (named children), `ListBlock` (one repeated type) and `StreamBlock` (mixed types). These nest freely.
- `min_num`, `max_num` and `block_counts` set limits, overall and per block type.
- Each block can have its own template, rendered with `{% include_block %}`.
- Chooser blocks reference pages and snippets (`PageChooserBlock`, `SnippetChooserBlock`).
- Every block accepts `label`, `icon`, `template` and `group`. Picker previews come from `preview_value`, `preview_template` and `description`. ([Block reference](https://github.com/wagtail/wagtail/blob/main/docs/reference/streamfield/blocks.md))

**Schema owner.** Code (Python). Changing a block's structure usually needs a data migration, because the JSON is not split into tables. ([StreamField](https://docs.wagtail.org/en/stable/topics/streamfield.html))

**Localization.** Document-level: each translation is its own object, linked by `translation_key` and `locale`, and each locale has its own page tree. Translations publish separately. ([Internationalisation](https://docs.wagtail.org/en/stable/advanced_topics/i18n.html))

- The built-in `simple_translation` copies a page as a draft for translators.
- `wagtail-modeltranslation` is the third-party field-level alternative.
- Fallback is at routing level: requests resolve through the translated homepage, and otherwise through the site root.

**Environments.** The official `wagtail-transfer` package moves "content … between multiple instances of a Wagtail project", configured with named sources such as `staging`. ([wagtail-transfer](https://github.com/wagtail/wagtail-transfer))

### Craft CMS 5

**Primitives.**

- Sections come in three kinds: singles, channels and structures. Entry types have been **global** since Craft 5: you define one once and reuse it across sections and Matrix fields, and its name and handle can be overridden locally. ([Entries](https://craftcms.com/docs/5.x/reference/element-types/entries.html))
- **Matrix** holds nested entries, so each block is a real element with its own entry type and can have its own URL. ([Matrix](https://craftcms.com/docs/5.x/reference/field-types/matrix.html))
  - View modes: cards, card grid, inline blocks, and an element index with an optional table view.
  - Min and max entries.
  - Entry-type groups for the author menu (5.8+).
  - Inline blocks can be versioned with their owner (5.7+).
- Fields are reusable across layouts, and one field can appear several times in a layout under different handles ("multi-instance"). ([Fields](https://craftcms.com/docs/5.x/system/fields.html))

**Schema owner.** The admin UI, but it is saved to **Project Config** YAML in `config/project/`. That is committed and applied with `craft up` or `project-config/apply`. Setting `allowAdminChanges=false` in production prevents drift. Content is not included. ([Project Config](https://craftcms.com/docs/5.x/system/project-config.html))

**Localization.** Sites have languages and are grouped into site groups. Each section's propagation method decides which sites an entry exists in. ([Sites](https://craftcms.com/docs/5.x/system/sites.html)) Each field has a translation method: not translatable, per site, per site group, per language, or custom. ([Fields](https://craftcms.com/docs/5.x/system/fields.html))

### Statamic

**Primitives.**

- Collections, taxonomies, globals, assets, users and forms are defined by **blueprints**: YAML in `resources/blueprints/`, editable in the control panel or by hand. A collection can have several blueprints, and each entry picks one.
- **Fieldsets** are reusable field groups. They can be imported more than once with a `prefix` to avoid handle collisions. ([Blueprints](https://statamic.dev/blueprints))
- **Replicator** stores an array of `type`-tagged sets. Sets can have preview images and icons in the picker, and rendering is one partial per set type. ([Replicator](https://statamic.dev/fieldtypes/replicator))
- **Bard** is a TipTap/ProseMirror rich-text field with sets placed inline in the prose. Text outside sets comes through as type `text`. ([Bard](https://statamic.dev/fieldtypes/bard))

**Localization.** "A localized entry should define where it originated, and will inherit any undefined values from its origin."

- Non-localizable fields are read-only in the localization.
- `propagate: true` creates localizations in the other sites automatically.

([Localizing entries](https://statamic.dev/tips/localizing-entries)) Each field is made localizable with a toggle. ([Multi-site](https://statamic.dev/multi-site))

**Environments.** By default, entries are Markdown files and collections are YAML files, so content and schema move through Git together. ([Collections](https://statamic.dev/collections)) The official Eloquent driver stores the data in a database instead. ([statamic/eloquent-driver](https://github.com/statamic/eloquent-driver))

### WordPress Gutenberg

**Primitives.**

- A block is registered in `block.json`. Its attributes are serialized as JSON in the block's HTML comment delimiter in `post_content`. ([block.json fundamentals](https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-json/))
- Nesting rules:
  - `parent` allows a block only as a direct child of the listed blocks.
  - `ancestor` (6.0) allows it at any depth inside them.
  - `allowedBlocks` (6.5) restricts a block's direct children.
- `render` (6.1) points to a PHP file for server-side rendering.

([Block metadata](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/))

**Reuse.** Patterns are groups of blocks offered as starting points. ([Patterns](https://developer.wordpress.org/themes/patterns/introduction-to-patterns/))

- **Unsynced** patterns (all theme-registered patterns) copy their blocks on insert.
- **Synced** patterns (formerly "reusable blocks") stay identical everywhere and are managed in the admin.
- **Pattern overrides** (6.6) let editors change chosen content in one use of a synced pattern without changing its layout. They are built on block bindings. ([Intro to pattern overrides](https://developer.wordpress.org/news/2024/06/an-introduction-to-overrides-in-synced-patterns/), via search snippet)
- **Template parts** are block-markup files in `/parts`, assigned to areas (header, footer, general). Site Editor edits are saved as `wp_template_part` posts in the database, which override the theme files. ([Template parts](https://developer.wordpress.org/themes/templates/template-parts/))

**Localization.** None in core. The roadmap still lists Phase 4 as "Core implementation for Multilingual sites". ([Roadmap](https://wordpress.org/about/roadmap/))

### Sulu (Symfony)

**Primitives.**

- A page template is an XML file in `config/templates/pages/` together with a Twig view. ([Templates](https://docs.sulu.io/en/latest/book/templates.html))
- Templates contain typed properties with options such as `mandatory`, `multilingual` and `visibleCondition`. Sections only group fields in the admin and do not affect the data model.
- **Blocks** have several types and can nest. Global blocks in `config/templates/blocks` are reused across templates.
- XInclude reuses parts of templates.
- References use property types such as `page_selection`, `snippet_selection` and `media_selection`.

**Reuse and slots.** Snippets are reusable content with their own templates. A snippet template can declare **snippet areas**, which an editor assigns to a default snippet per webspace. Twig then loads the snippet "for a specific area and webspace", for example footer social links. Each area sets whether changes invalidate the cache. ([Default snippets](https://github.com/sulu/sulu-docs/blob/3.x/cookbook/default-snippets.rst))

**Storage (Sulu 3.0, released 26 Nov 2025).** PHPCR was replaced by Doctrine ORM with JSON fields: "Pages, articles, and snippets are now standard Doctrine entities." Content has dimensions (language, publication stage, version), and they are merged at render time so a localized page inherits non-translated data. ([Sulu 3.0 released](https://sulu.io/blog/sulu-3-0-released))

The source confirms this ([`DimensionContentInterface`](https://github.com/sulu/sulu/blob/3.0/packages/content/src/Domain/Model/DimensionContentInterface.php), [`TemplateInterface`](https://github.com/sulu/sulu/blob/3.0/packages/content/src/Domain/Model/TemplateInterface.php), [`TemplateDataMapper`](https://github.com/sulu/sulu/blob/3.0/packages/content/src/Application/ContentDataMapper/DataMapper/TemplateDataMapper.php)):

- A dimension row has a nullable `locale`, a `stage` (`draft` or `live`), a `version`, a `ghostLocale` and `availableLocales`.
- Template data is a `templateKey` plus a `templateData` array.
- On save, properties are split between the *unlocalized* dimension (locale null) and the *localized* one by their `multilingual` flag.

**Localization.** Nested localizations define fallbacks, for example `en-us` → `en`. A page with no translation in the current locale is a "ghost" page that shows the closest available language. ([Localization](https://docs.sulu.io/en/latest/book/localization.html)) Shadow pages exist as a dimension feature (`ShadowDataMapper` in the 3.0 source).

**Environments.** Templates and structure are XML in Git, and content lives in the database. ([Sulu 3.0 released](https://sulu.io/blog/sulu-3-0-released))

## Patterns and trade-offs for a Sylius CMS serving agencies and merchants

1. **The block tree is the shared core; store it as a document.** Each system stores an ordered, typed list or tree of blocks as JSON on the owner document: Shopify templates, Storyblok `content`, Sanity arrays, Wagtail StreamField, Payload blocks and Sulu 3 `templateData`. Two systems store it differently:
   - Craft makes each block a real element (nested entries). Blocks then get identity, URLs and per-block versioning, at the cost of more rows and joins.
   - Gutenberg serializes blocks into HTML comments, which is hard to query and validate.

   For Doctrine on Sylius 1.14, Sulu 3's approach is the closest proven model: entities for the documents, with a JSON column for block data. Give every block a stable key, like Sanity's `_key` or Storyblok's `_uid`, so editing, diffing, translation and transfer can target it.

2. **Developers should own block types in code. Editors compose instances.** Schema-in-code (Shopify, Sanity, Payload, Wagtail, Sulu, Gutenberg) gives agencies Git review, typed rendering and deployable changes. Admin-UI schemas (Storyblok, Contentful) need a separate sync step: CLI push and pull, migrations, or the Merge app. Craft and Statamic bridge the two by saving UI edits as committed files and locking them in production (`allowAdminChanges=false`). Sylius favours code: block types as services or config with a Twig template, the same pattern as Wagtail's per-block `template` and Statamic's per-set partials.

3. **Guardrails are how flexibility stays usable.** Every mature system constrains composition:
   - **Allowed children:** Storyblok restricts blocks, folders or tags; WordPress has `parent`, `ancestor` and `allowedBlocks`; Payload has `filterOptions`.
   - **Count limits:** Wagtail has `block_counts`; Craft has min and max entries; Shopify allows 25 sections and 50 blocks.
   - **Fixed blocks:** Shopify's static blocks can be hidden but not deleted.
   - **Placement:** Shopify's `enabled_on` and `disabled_on`.

   Shopify's split is a useful model. Section blocks are flat, one level, simple. Theme blocks can nest, for richer layouts. Each block type should declare where it may appear and what it may contain.

4. **Offer both kinds of reuse, and name them differently.**
   - **Copy-on-insert presets or patterns:** Shopify section presets, Storyblok presets, WordPress unsynced patterns, Contentful Studio patterns. Editors get a good starting point and can never break other pages.
   - **Synced references:** WordPress synced patterns, Sulu snippets, Wagtail snippets, Sanity references, Shopify section groups. Edit once, change everywhere.

   WordPress pattern overrides are a middle ground: a shared layout with chosen fields editable per use. This maps directly onto v2's "layout presets" (copy) versus shared content.

5. **Slots in existing pages are a solved shape.** Shopify section groups (layout-level JSON lists of sections, with market overrides) and Sulu snippet areas (named areas assigned per webspace, loaded from Twig, with a cache-invalidation flag per area) are both "named regions in a developer layout that editors fill". Both systems hand the host page's resource to the block in their own way. A Shopify section that accepts app blocks should have at most one resource setting of each type (for example, one product setting), so app blocks can autofill from the page's product. This supports v2's need for slot content to use the host page's Twig context such as `product`.

6. **Localization: share the structure and translate the leaves, with explicit fallback.** Three models appear:
   - **Field-level in one document:** Contentful, Payload, Storyblok field-level, Sanity internationalized-array, Craft translation methods, Shopify.
   - **Document-level linked translations:** Wagtail, Sanity document-internationalization, Storyblok folders.
   - **Hybrid with inheritance:** Statamic origin entries, Sulu 3's unlocalized and localized dimensions.

   For page builders, the common default is a shared block tree with translated leaf fields. Storyblok does not even allow a Blocks field to be translatable. Payload makes this an explicit choice: localize the Blocks field to get a separate layout per locale, or localize only its children to share the layout. Fallback rules need to be precise: Contentful falls back only when a value is *absent*, and an explicit empty value blocks it. Locale chains (Contentful, Payload, Sulu nesting) and origin inheritance (Statamic) are the two proven shapes. Sylius already has per-locale translation entities with a fallback locale; a Sulu-style split (shared JSON plus per-locale JSON) would fit that.

7. **Plan schema changes for JSON-stored blocks.** Wagtail and Payload warn that changing a block's structure, or making a field localized, needs a data migration because the JSON is not normalised. Sanity tolerates schema drift because its store is schemaless, and pushes the work into idempotent migration scripts. v2 needs a story for block-type versioning or upcasting from the start.

8. **Moving content between environments is where systems differ most.**
   - **Schema in Git, content in the database, no built-in transfer:** Payload, Craft, Sulu.
   - **Content as files in Git:** Shopify, Statamic. Merchant edits come back as commits, at the risk of conflicts. WordPress Site Editor edits go to the database and override the theme files.
   - **Dedicated transfer tools:** `wagtail-transfer`, Storyblok CLI and Management API sync, Sanity dataset export and import, Contentful clone-and-alias.

   For a Sylius plugin, the realistic pattern is schema in code plus an explicit export and import of content with stable IDs and references, as `wagtail-transfer` does. Treating content as files in Git would fight Sylius's database-centred admin.

9. **Editor experience depends on picker previews and visual editing.** Block pickers with preview images or thumbnails appear in Wagtail (`preview_value`, `description`, `group`), Sanity (`insertMenu` grid with `previewImageUrl`), Statamic (set preview images) and Shopify (preset `category`). Live visual editing appears in Shopify's theme editor, Storyblok's Visual Editor and Contentful Studio. These are cheap to declare per block type and matter most to merchants.

10. **A seam for third-party blocks.** Shopify's `@app` block type lets installed apps contribute blocks to any section that opts in. Contentful Studio lets the host app register components. The Sylius equivalent is other plugins registering block types through a tagged service or interface. That is also the natural seam for the future sandboxed code block.

## Confidence and gaps

- **Contentful** was reached only through search-result extracts of contentful.com pages, because direct fetches were rate-limited or bot-checked. The 50-fields limit and the fallback rules are each confirmed by two official pages.
- **Shopify:** the fallback of untranslated theme content to the default language comes from a Help Center search extract. How theme JSON settings are exposed as translatable resources was not confirmed on the page fetched.
- **Sulu:** shadow pages are inferred from `ShadowDataMapper` in the 3.0 source, not from 3.0 docs. The localization page fetched is the "latest" docs, which may describe 2.x.
- **Not researched in depth:** Storyblok pipelines and branches, Craft content-transfer plugins, WordPress WXR export, and the internals of Shopify metaobject templates.
