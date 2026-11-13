# Raw HTML is an opt-in Embed code block, while admin-editable Twig stays out

The plugin ships an Embed code block that holds raw HTML, scripts included, and prints it unescaped in the shop. It's switched off until a project enables it in config. The builder's preview shows a placeholder in its place, so third-party scripts don't run inside the builder.

Admin-editable Twig was ruled out of v2.0 for security, UX and maintainability, so a reader may ask why raw HTML is allowed. The risks differ. Twig typed into the admin runs on the server, and a sandbox escape can mean code execution and access to every shop's data. Raw HTML runs only in shoppers' browsers, and Sylius 1.x OSS has no roles, so every admin is already fully trusted. Merchants also need embeds often, such as review widgets, social feeds, newsletter forms and iframes, and without this block every embed would need a developer.

It's opt-in because a compromised admin account could use it to inject scripts into checkout and skim payment details. A project that enables it accepts that risk.

## Considered Options

- **On by default.** Editors could use it right away, but every install would carry the skimming risk, whether or not it needs embeds.
- **No HTML block.** The safest option, but agencies would write a block type for each embed.
- **Sanitised HTML only.** Safe, but almost every embed needs a script or an iframe, so it would be useless.

## Consequences

- Turning it off later follows the rule for any disabled block type: editors can no longer add it, and blocks already in trees keep rendering. A project that wants its embeds gone removes them from the content.
- Patched-in previews can't run embed scripts reliably anyway, so the placeholder costs editors little.
- The seam for a future sandboxed code block is unaffected. That block would be another block type whose template is stored as data.
