# Editor technology for a visual builder in a Symfony admin

Research for [#191](https://github.com/Setono/SyliusCMSPlugin/issues/191), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186). Gathered 2026-11-13. This compares options; it does not decide. A later decision ticket picks.

**Question.** Which existing technologies could power v2's visual builder and rich-text editing inside the Sylius 1.14 admin (Semantic UI, jQuery, Webpack Encore), with a later Sylius 2.x port (Bootstrap, Twig Hooks, Symfony UX Live Components)?

## Short answer

No embeddable page builder fits cleanly. The healthy ones (Puck, GrapesJS) bring their own render model: React components for Puck, and a free-form HTML/CSS tree for GrapesJS. That clashes with a Twig-first storefront and a typed, API-ready block schema. Of the rest, Craft.js is dormant and Builder.io is a hosted SaaS.

The options that fit the platform best are:

- a **server-driven builder**: Symfony Forms and Twig, upgraded with Stimulus or Live Components. The block schema stays in PHP, and the preview is the real Twig output.
- **Tiptap** for rich-text fields. It is MIT and framework-agnostic, stores JSON, and has a maintained PHP package that renders and sanitises that JSON on the server.

**Puck** is the strongest off-the-shelf builder if the decision favours editor UX over Twig fidelity.

## Context that shapes the comparison

- **Sylius 1.14 admin stack.** The `package.json` on the 1.14 branch has `jquery`, `semantic-ui-css` and `@symfony/webpack-encore`. `composer.json` requires `symfony/webpack-encore-bundle ^1.15`, Symfony `^5.4.21 || ^6.4` and PHP `^8.1`. There are no Stimulus or Symfony UX packages. ([composer.json@1.14](https://github.com/Sylius/Sylius/blob/1.14/composer.json), [package.json@1.14](https://github.com/Sylius/Sylius/blob/1.14/package.json))
- **Sylius 2.x admin stack.** The 2.3 branch requires `sylius/twig-hooks`, `symfony/stimulus-bundle`, `symfony/ux-live-component`, `symfony/ux-twig-component` and `symfony/ux-autocomplete` (`^2.36 || ^3.0`), plus `symfony/webpack-encore-bundle ^2.4`. ([composer.json@2.3](https://github.com/Sylius/Sylius/blob/2.3/composer.json))
- **Symfony UX version split.** `symfony/ux-live-component` 3.x requires PHP `>=8.4` and `symfony/property-access ^7.4|^8.0`. 2.36 requires PHP `>=8.1` and accepts Symfony `^5.4.5|^6.0|^7.0|^8.0`. So on Sylius 1.14 (Symfony 6.4 at most), only the **UX 2.x line** installs. That line's last release is v2.36.2 (2026-07-08), and the `2.x` branch still gets commits. ([composer.json v2.36.0](https://github.com/symfony/ux-live-component/blob/v2.36.0/composer.json), [composer.json v3.0.0](https://github.com/symfony/ux-live-component/blob/v3.0.0/composer.json), [UPGRADE-3.0.md](https://github.com/symfony/ux/blob/3.x/UPGRADE-3.0.md))
- **Licence lens.** The plugin is MIT. MIT, BSD-3-Clause and Apache-2.0 dependencies keep the whole bundle permissive. GPL dependencies can be combined with MIT code, but the distributed combination carries GPL obligations, which pass to every shop that installs the plugin.

## Comparison table

Health figures are a GitHub API snapshot from 2026-11-13. "Open" counts issues plus PRs. Bundle sizes are JS only (CSS excluded), minified and gzipped (`-9`) with esbuild 2026-11-13 for a minimal setup (see [Method](#method-for-bundle-sizes)).

| Option | Licence (MIT-plugin fit) | Maintenance | Storage format | Extension model | Live preview | Editor-UI i18n | JS gz | Sylius 1.14 fit | Sylius 2.x port |
|---|---|---|---|---|---|---|---|---|---|
| **Puck** (`@puckeditor/core` 0.24.0) | MIT (yes) | Active. 13.7k stars, 208 open, v0.24.0 2026-10-29, pre-1.0 | JSON: `root.props`, `content[{type, props}]`, nested `slot` fields (`zones` being deprecated) | `config.components` with fields (text, select, array, object, external, custom, slot, richtext) and a React `render` | Same-origin iframe (default on), rendered by React | English only; `dictionary` prop overrides strings | 383 kB incl. React 19 (69 kB) | Needs React in the admin; its UI is self-styled, so it doesn't fight Semantic UI | Ports as-is (an island, not tied to the admin CSS) |
| **GrapesJS** (0.23.9) | BSD-3-Clause (yes). The company now sells a commercial "Studio SDK" | Active. 26.4k stars, 31 open, v0.23.9 2026-11-03 | JSON `projectData` (`assets`, `styles`, `pages`): an HTML-like component tree plus CSS rules. Docs warn not to persist the HTML/CSS | `DomComponents.addType` (`isComponent`, model `defaults`, `traits`, `view`), blocks, plugins | Iframe canvas; storefront CSS/JS injected via `canvas.styles` and `canvas.scripts` | i18n module; 22 locale files in the repo | 301 kB | No framework needed (vanilla); heavy | Same as 1.14 |
| **Craft.js** (`@craftjs/core` 0.2.12) | MIT (yes) | **Dormant.** Last commit and release 2025-02-14; 227 open | JSON node map from `query.serialize()` | React components with `craft` config; you build the whole editor UI | React tree in the host page; no iframe in core | None (you build the UI) | 98 kB incl. React | React, plus building a lot of UI yourself | Same |
| **Builder.io SDK** (`@builder.io/sdk-react` 5.4.4) | SDKs MIT, but the editor is a hosted SaaS | Active. Releases 2026-11-12 | JSON `blocks`, stored and served by Builder's Content API | Register your components with the SDK | Builder's web app loads your site in an iframe and sends patches via `postMessage` | n/a | n/a | **Not embeddable.** Editor and content live on builder.io | n/a |
| **Tiptap** (3.33.1, on ProseMirror) | MIT core (yes). Some extensions and Cloud features are paid | Very active. 39k stars, 933 open, v3.33.1 2026-11-10 (roughly weekly) | ProseMirror JSON (`type`, `attrs`, `content`, `marks`) or HTML. Strict schema | `Node.create`, `Mark.create`, `Extension`; node views | Inline WYSIWYG in the field | Headless: no built-in UI, so strings are yours | 124 kB (core + StarterKit) | Vanilla JS mounts in any form field | Same; also wraps in a Stimulus controller |
| **Lexical** (0.53.0) | MIT (yes) | Active. 24k stars, 517 open, about monthly 0.x releases. Repo moved to `meta/lexical` | EditorState JSON; HTML export via `@lexical/html` (JS only; headless needs a DOM shim) | Custom node classes declared with `$config` | Inline WYSIWYG | No built-in UI | 130 kB (rich-text, history, list, link, html) | Vanilla core works, but the ecosystem is React-first | Same |
| **CKEditor 5** (49.1.0) | **GPL-2.0-or-later or commercial.** The `'GPL'` licence key works for self-hosted builds only and shows a "Powered by CKEditor" badge | Very active. 10.5k stars, 693 open, v49.1.0 2026-11-04 | HTML (`getData()`); internal model | Plugins: schema, conversion, widgets | Inline WYSIWYG | **41 fully translated languages** | 193 kB (classic + basics) | Works vanilla; no Symfony bundle for v5 (FOSCKEditorBundle is CKEditor 4 only) | Same |
| **Editor.js** (2.31.7; 2.32 in RC) | Apache-2.0 (yes) | Active. 32k stars, 708 open, v2.32.0-rc.3 2026-11-01 | JSON `{time, blocks[{type, data}], version}`. Inline formatting stays as **HTML strings inside `data`** | Block Tools, Inline Tools, Block Tunes | Inline, block-by-block | `i18n.messages` dictionary + RTL; no bundled locales | 80 kB (+ header, list) | Vanilla; PHP lib `editorjs-php` stale (last release 2020) | Same |
| **Quill** (2.0.3) | BSD-3-Clause (yes) | **Stalled.** Last release 2024-11-30, last commit 2025-07-25 | Delta JSON (`ops` with `insert`/`attributes`); `getSemanticHTML()` | Parchment blots, modules | Inline | No i18n API in the docs (not verified further) | 60 kB | Vanilla | Same |
| **Symfony UX Live Components + Turbo** | MIT (yes) | Active. UX v3.7.2 2026-11-08. **1.14 must stay on 2.x** (v2.36.2 2026-07-08). Turbo v8.0.23 2026-01-29 | Whatever we model: Doctrine and Symfony Forms are the source of truth | PHP block types → Form types and Twig components; `LiveCollectionType` adds and removes rows | Server re-renders Twig over Ajax and morphs the DOM (idiomorph). Can also drive an iframe to a shop preview route | Symfony Translator, so all Sylius admin locales | 51 kB (Stimulus + Live + Turbo) | Must add `stimulus-bundle` and UX 2.x to the 1.14 admin | **Native.** Sylius 2.x admin already ships Live Components |

## Findings by family

### 1. Embeddable page builders

**Puck** is the healthiest builder that matches a typed content model.

- **Licence and model.** MIT ([LICENSE](https://github.com/puckeditor/puck/blob/main/LICENSE)). It is "just a React component" for React `^18 || ^19` (npm peer dependency). Its data is a JSON tree of `{type, props}` with `root.props`. Nesting moves from `zones` to `slot` fields. ([Data](https://puckeditor.com/docs/api-reference/data-model/data))
- **Mapping to a server schema.** This maps almost one-to-one onto a server-side "block type + validated props" schema. PHP could export the block-type registry as Puck `config`.
- **Fields.** Fields include `external` (pick from an API, good for products or taxons), `custom`, `slot` and a `richtext` field "built with Tiptap" with configurable `tiptap.extensions` and optional inline `contentEditable`. ([Fields](https://puckeditor.com/docs/api-reference/fields), [RichText](https://puckeditor.com/docs/api-reference/fields/richtext))
- **Preview.** It renders in a same-origin iframe, on by default, and copies host styles in. ([Puck component API](https://puckeditor.com/docs/api-reference/components/puck))
- **Trade-off: React vs Twig.** Every block's preview is a React `render`, while the storefront is Twig. We would either keep two renderers per block (React and Twig) or write a generic React render that fetches server-rendered Twig HTML for each block. That second approach is a pattern we'd invent, not something Puck documents.
- **i18n.** The UI is US English only, overridable through a `dictionary` prop. ([Localization](https://puckeditor.com/docs/integrating-puck/localization))
- **Stability.** Puck is pre-1.0 (0.22 → 0.24 within four months), so expect migration work. It is also the largest bundle measured.

**GrapesJS** is mature and framework-free, but it is a web-page designer.

- **Licence.** BSD-3-Clause ([LICENSE](https://github.com/GrapesJS/grapesjs/blob/dev/LICENSE)). The README now points embedders to a commercial "Studio SDK".
- **Storage.** It stores `projectData` JSON. The docs say "The project data doesn't contain the HTML/CSS of your pages" and warn "never rely on HTML/CSS as a persistence layer". ([Storage](https://grapesjs.com/docs/modules/Storage.html))
- **Custom types.** Types are defined with `addType` (`isComponent`, `defaults`, `traits`, `view`). ([Components](https://grapesjs.com/docs/modules/Components.html))
- **Canvas.** The canvas is an iframe; `canvas.styles` and `canvas.scripts` are appended to "the `<head>` of the iframe". ([config.ts](https://github.com/GrapesJS/grapesjs/blob/dev/packages/core/src/canvas/config/config.ts))
- **i18n.** 22 locales ship in `src/i18n/locale`. ([I18n](https://grapesjs.com/docs/modules/I18n.html))
- **Trade-off.** Its model is an HTML/CSS component tree with a style manager. That suits free-form design but fights a typed, API-ready block schema rendered by Twig templates. Constraining it to custom types only is possible, but then most of what it offers goes unused.

**Craft.js** is a React framework for building your own page editor ([README](https://github.com/prevwong/craft.js)).

- **No activity since 2025-02-14.** That was the last commit and release (React 19 support), and 227 issues and PRs are open. Not a safe foundation.

**Builder.io** is a hosted platform, not an embeddable editor.

- **Where it runs.** "Builder loads your website in an `iframe` within the Builder web application". Edits are sent to the page as `postMessage` patches, and content is delivered by Builder's APIs. ([How Builder works](https://www.builder.io/c/docs/how-builder-works-technical))
- **Fit.** It can't live inside the Sylius admin or store content in the shop database, so it is out. Its **preview pattern** is still worth copying: an iframe of the real storefront, patched over `postMessage`.

### 2. Rich-text editors

**Tiptap** is the strongest fit for rich-text fields.

- **Licence and framework.** MIT core, with "paid Tiptap extensions" and Cloud features on top. It works with "React, Vue, Svelte, plain JS". ([Overview](https://tiptap.dev/docs/editor/getting-started/overview))
- **Strict schema.** "You can't use any HTML element or attribute that is not defined in your schema." ([Schema](https://tiptap.dev/docs/editor/core-concepts/schema))
- **Server side.** The decisive factor is `ueberdosis/tiptap-php` (MIT, v2.2.1 2026-10-19). It converts Tiptap JSON to HTML and back, and sanitises content in PHP ([README](https://github.com/ueberdosis/tiptap-php)). So we can store JSON (API-ready), render it in Twig and sanitise on save without Node.
- **Shared with Puck.** Puck's richtext field also uses Tiptap.
- **ProseMirror.** It is MIT, but its development moved off GitHub to `code.haverbeke.berlin`; the GitHub repos were archived 2026-04-01 ([README](https://github.com/ProseMirror/prosemirror)). Tiptap hides that detail.

**Lexical** is MIT and active, but has two gaps for us. ([README](https://github.com/meta/lexical))

- **Still 0.x.** The latest release is 0.53.0.
- **No PHP renderer.** Its JSON is generated from each node's `$config`, and HTML export is a JS function (`$generateHtmlFromNodes`). Running it outside a browser needs `@lexical/headless` with a DOM. ([serialization.md](https://github.com/meta/lexical/blob/main/packages/lexical-website/docs/serialization/serialization.md))
- **React-first.** The framework-agnostic core exists, but the docs and examples lead with React.

**CKEditor 5** has the best out-of-the-box UX and i18n, with 41 fully translated UI languages ([UI language](https://ckeditor.com/docs/ckeditor5/latest/getting-started/setup/ui-language.html)). Its licence is the problem.

- **Licence.** It is "GNU General Public License Version 2 or later … or commercial license terms" ([LICENSE.md](https://github.com/ckeditor/ckeditor5/blob/master/packages/ckeditor5/LICENSE.md)).
- **Licence key.** The `'GPL'` key "works only with the self-hosted distribution", adds a "Powered by CKEditor" logo, and premium features need a commercial licence. ([Licence key](https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-key-and-activation.html))
- **Effect on adopters.** Bundling it puts GPL obligations on every shop that installs the plugin.
- **Symfony integration.** The common bundle (FOSCKEditorBundle) still targets CKEditor 4 ([README](https://github.com/FriendsOfSymfony/FOSCKEditorBundle)).

**Editor.js** is a block-style editor: Apache-2.0, clean JSON, an `i18n.messages` dictionary and RTL support. ([Base concepts](https://editorjs.io/base-concepts/), [i18n](https://editorjs.io/i18n/))

- **Inline HTML in JSON.** Inline formatting is stored as HTML inside block JSON, for example `<mark class="cdx-marker">` in a paragraph's `text` ([example.html](https://github.com/codex-team/editor.js/blob/next/example/example.html)). We would still need HTML sanitising.
- **Stale PHP backend.** `editor-js/editorjs-php` was last released 2020-06-12.
- **Overlap with the builder.** Its block model competes with the builder rather than filling a field inside it.

**Quill** is BSD-3-Clause with a Delta JSON format ([Delta](https://quilljs.com/docs/delta)).

- **Stalled.** There has been no release since 2024-11-30 and no commit since 2025-07-25, so it isn't recommended for a new v2.

### 3. Server-driven: Symfony UX Live Components and Turbo

- **How it works.** A change triggers "an Ajax call … to get a fresh copy of our component. That HTML will replace the current HTML", with 150 ms debounce by default. Updates are morphed with idiomorph. ([Live Components](https://symfony.com/bundles/ux-live-component/current/index.html))
- **Forms.** `ComponentWithFormTrait` binds a Symfony Form, and `LiveCollectionType` "adds an 'Add' and a 'Delete' button for each row … out of the box". That is the core of an add-, remove- and reorder-blocks form, with each block type as a PHP Form type.
- **Nested components.** Child components re-render independently ("each component exists in its own, isolated universe"). Parents and children communicate via `emit`/`emitUp` and `#[LiveListener]`, or via `dataModel`. This suits a block tree where each block's settings panel is its own component.
- **JS widgets inside components.** Third-party widgets (such as a Tiptap field) need `data-live-ignore` so re-renders don't clobber them. The docs give no rich-text-specific guidance.
- **Strengths.**
  - The block schema, validation, translations and Twig rendering all stay in PHP.
  - The preview is the real storefront Twig, which serves host-page context and the Twig-first rule in #186.
  - The admin UI uses the Symfony translator.
  - Sylius 2.x's admin is already built on this stack, so the port is the cheapest of all options.
- **Costs.**
  - We build the builder UX ourselves: drag-and-drop ordering, block picker, tree or outline, and canvas selection. No packaged "page builder" exists in Symfony UX.
  - Every non-deferred change costs a server round trip.
  - On 1.14 we must introduce Stimulus and UX into an admin that has neither, and stay on UX 2.x (PHP 8.4 and Symfony 7.4 are needed for 3.x).
- **Turbo.** Turbo (MIT, v8.0.23 2026-01-29, no release since) adds Frames and Streams. These are useful for lazy panels or a preview frame, but are not needed for the Live Component approach, and Sylius 2.x does not require `symfony/ux-turbo`.

## Strongest candidates and their trade-offs

| Candidate stack | For | Against |
|---|---|---|
| **A. Server-driven builder** (Forms + Live Components/Stimulus) **+ Tiptap fields** (JSON, rendered and sanitised by tiptap-php) **+ iframe preview of a shop route** | Schema, validation and rendering all in PHP and Twig; preview is the real storefront with host-page context; API-ready JSON; all-MIT; cheapest Sylius 2.x port; smallest JS | Most UX to build ourselves (drag-and-drop, block picker, selection sync); Ajax latency per change; adds Stimulus and UX 2.x to the 1.14 admin |
| **B. Puck shell** (block config generated from the PHP registry) **+ its Tiptap richtext field** | Polished drag-and-drop builder for free; JSON maps onto typed blocks; MIT; active; independent of admin CSS, so it ports well | React in a jQuery admin; preview is React, not Twig (two renderers per block, or a fetch-Twig-HTML bridge); pre-1.0 churn; about 380 kB gzipped |
| **C. GrapesJS** | Mature, vanilla, iframe canvas with storefront CSS, 22 locales | HTML/CSS free-form model fights a typed, API-ready block schema and Twig templates; heavy; vendor focus moving to the commercial Studio SDK |

**Not recommended:**

- Craft.js: dormant.
- Builder.io: hosted SaaS.
- Quill: stalled.
- CKEditor 5: GPL obligations on adopters, and a licence key and badge.
- Editor.js as the field editor: HTML inside its JSON, a stale PHP backend, and a block model that overlaps the builder.
- Lexical: viable, but it has no PHP renderer and is still 0.x, so Tiptap dominates it for this stack.

## Open questions for the decision ticket

- **Merchant UX.** Is a polished drag-and-drop canvas (favours B) worth a second rendering path and React in the admin, or is a form-and-outline builder with a live Twig preview (A) good enough?
- **Preview location.** Inline in the admin DOM, which needs storefront CSS scoped into the admin, or an iframe of a shop preview route, which needs a draft token and a refresh or `postMessage` channel?
- **Stimulus on 1.14.** Do we accept adding Stimulus and UX 2.x to the 1.14 admin now, which pays off at the Sylius 2.x port?

## Method for bundle sizes

Each option was installed from npm into an empty folder and bundled with `esbuild --bundle --minify --format=esm` (CSS and SVG loaders stubbed). The output was measured with `gzip -9`. Versions:

- React 19.3.1
- `@puckeditor/core` 0.24.0
- `@craftjs/core` 0.2.12
- `grapesjs` 0.23.9
- `@tiptap/core` 3.33.1
- `lexical` 0.53.0
- `ckeditor5` 49.1.0
- `@editorjs/editorjs` 2.31.7
- `quill` 2.0.3
- `@hotwired/turbo` 8.0.23
- `@symfony/ux-live-component` 3.7.2 (with `@hotwired/stimulus` 3.2.2)

Entry points used each library's minimal documented setup:

- Puck: the `<Puck>` component.
- Tiptap: StarterKit.
- CKEditor: ClassicEditor with Essentials, Paragraph, Bold, Italic, Heading, Link, List and BlockQuote.
- Editor.js: the header and list tools.
- Lexical: rich-text, history, list, link and html.

These are comparative sizes, not production budgets.

| Bundle | Minified | Min + gzip |
|---|---|---|
| React 19 alone (baseline) | 223 kB | 69 kB |
| Puck + React | 1,274 kB | 383 kB |
| Craft.js + React | 314 kB | 98 kB |
| GrapesJS | 1,144 kB | 301 kB |
| Tiptap + StarterKit | 392 kB | 124 kB |
| Lexical (+ rich-text, history, list, link, html) | 396 kB | 130 kB |
| CKEditor 5 classic + basics | 750 kB | 193 kB |
| Editor.js + header + list | 299 kB | 80 kB |
| Quill (snow) | 205 kB | 60 kB |
| Stimulus + Live Component + Turbo | 191 kB | 51 kB |

## Sources

GitHub repo metadata, licences and releases came from the GitHub REST API (`gh api repos/<owner>/<repo>` and `/releases`) on 2026-11-13. npm versions and peer dependencies came from `npm view`.

**Puck**
- https://github.com/puckeditor/puck
- https://puckeditor.com/docs
- https://puckeditor.com/docs/api-reference/data-model/data
- https://puckeditor.com/docs/api-reference/components/puck
- https://puckeditor.com/docs/api-reference/fields
- https://puckeditor.com/docs/api-reference/fields/richtext
- https://puckeditor.com/docs/integrating-puck/localization

**GrapesJS**
- https://github.com/GrapesJS/grapesjs
- https://grapesjs.com/docs/modules/Storage.html
- https://grapesjs.com/docs/modules/Components.html
- https://grapesjs.com/docs/modules/I18n.html
- https://github.com/GrapesJS/grapesjs/blob/dev/packages/core/src/canvas/config/config.ts

**Craft.js**
- https://github.com/prevwong/craft.js

**Builder.io**
- https://github.com/BuilderIO/builder
- https://www.builder.io/c/docs/how-builder-works-technical

**Tiptap and ProseMirror**
- https://github.com/ueberdosis/tiptap
- https://tiptap.dev/docs/editor/getting-started/overview
- https://tiptap.dev/docs/editor/core-concepts/schema
- https://github.com/ueberdosis/tiptap-php
- https://github.com/ProseMirror/prosemirror

**Lexical**
- https://github.com/meta/lexical
- https://github.com/meta/lexical/blob/main/packages/lexical-website/docs/serialization/serialization.md

**CKEditor 5**
- https://github.com/ckeditor/ckeditor5/blob/master/packages/ckeditor5/LICENSE.md
- https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-key-and-activation.html
- https://ckeditor.com/docs/ckeditor5/latest/getting-started/setup/ui-language.html
- https://github.com/FriendsOfSymfony/FOSCKEditorBundle

**Editor.js**
- https://github.com/codex-team/editor.js
- https://editorjs.io/base-concepts/
- https://editorjs.io/i18n/
- https://github.com/editor-js/editorjs-php

**Quill**
- https://github.com/slab/quill
- https://quilljs.com/docs/delta

**Symfony UX and Turbo**
- https://symfony.com/bundles/ux-live-component/current/index.html
- https://github.com/symfony/ux
- https://github.com/symfony/ux/blob/3.x/UPGRADE-3.0.md
- https://github.com/symfony/ux-live-component
- https://github.com/hotwired/turbo

**Sylius**
- https://github.com/Sylius/Sylius/blob/1.14/composer.json
- https://github.com/Sylius/Sylius/blob/1.14/package.json
- https://github.com/Sylius/Sylius/blob/2.3/composer.json
