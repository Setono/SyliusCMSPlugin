# The builder is our own React app, previewing Twig output in a same-origin iframe

The builder is a client-side app we write in React and TypeScript. It holds the entry's working copy as JSON in the browser, and the server renders the preview with the same Twig templates shoppers see. The preview is an iframe of the real shop page, loaded from the admin's own host. A signed token turns on a preview mode, which forces the channel, locale and sample object and renders the working copy. Preview mode also injects a bridge script that talks to the builder only by `postMessage`. Rich text is edited with Tiptap, using only its MIT core and MIT extensions, and `ueberdosis/tiptap-php` renders and sanitises it on the server.

We chose this because the builder the prototype settled on needs instant interaction in the browser:

- drag and drop with inputs in the tree's rows
- multi-select and undo
- text typed in place in the preview, with selection linked both ways

It also needs the preview to be the real shop page, rendered by the storefront's own Twig, including a snippet's host page. No packaged builder renders that preview, and a server-driven builder pays a round trip for every interaction. The preview loads from the admin's own host so that it still works when channels have their own hostnames, with no third-party cookies and no framing rules on the shop.

## Considered Options

- **Symfony UX Live Components**, the server-driven approach the Sylius 2.x admin uses.
  - For: schema, validation and rendering would all stay in PHP, and the 2.x port would be the cheapest.
  - Against: every change costs an Ajax round trip, and undo, drag and drop with inputs, and Tiptap instances all fight its re-render model.
  - Against: the iframe preview sits outside the component's DOM, so the preview doesn't get Live Components' main benefit.
  - Against: on Sylius 1.14 it's limited to UX 2.x, which is end-of-life, and no working setup in the 1.14 admin is known.
- **Puck.** A polished React builder whose JSON maps well onto typed blocks. Its canvas renders blocks with React, so the real shop page can't be the preview unless the canvas is replaced. It is also pre-1.0.
- **GrapesJS.** A mature designer, but its free-form HTML and CSS model fights typed block types and Twig rendering.
- **A preview iframe on the channel's own hostname.** This gives the truest URL, but the iframe is then cross-origin: browsers block third-party cookies, and every shop must let the admin frame it.
- **CKEditor 5 for rich text.** Its GPL licence would pass obligations on to every shop that installs the plugin.

## Consequences

- **Look.** The builder has its own full-screen layout and CSS, with no Semantic UI or jQuery, so it ports to Sylius 2.x unchanged. Only preview mode's hooks into the shop and the links into the builder need a 2.x adapter.
- **Assets.** The plugin ships the builder prebuilt, as compiled assets committed to the repo and checked in CI. A host project needs no Node, Encore or Stimulus setup. We maintain a JS toolchain and JS tests alongside the PHP.
- **Fields.** Every entry is edited in the builder, including entries whose content type has no block tree. Block types and content types declare their fields with field types. The registry exports them to the builder's widgets as a JSON schema, and the server validates on save from the same declarations.
- **Custom field types.** A project's own field type is a PHP class plus a widget written as a custom element, loaded from a script the project lists in the plugin's config.
- **Server endpoints.** The builder talks to plain JSON controllers under the admin firewall, not to API Platform. The future headless API is a separate design.
- **Markers.** In preview mode, the plugin brackets each block's output with HTML comment markers that carry its key. Fields printed through the plugin's Twig field helper can be typed in place. Block templates need no code specific to preview mode.
- **Storefront JS.** The preview patches only the changed blocks into the page. So block types with storefront JavaScript, such as carousels, must also initialise on the bridge's event, not only on page load.
- **UI strings.** The builder's UI text lives in the plugin's Symfony translation files, exported to the builder as JSON.
