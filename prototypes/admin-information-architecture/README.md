# PROTOTYPE: admin information architecture (throwaway, never merge)

Built for [Admin information architecture](https://github.com/Setono/SyliusCMSPlugin/issues/210), part of the map [Map: Sylius CMS Plugin v2 spec](https://github.com/Setono/SyliusCMSPlugin/issues/186). It lives on the throwaway `prototype/admin-information-architecture` branch as a primary source and is never merged.

## Question

How are pages, collections, snippets, slots, media and presets organised and navigated in the Sylius 1.14 admin, and how do editors get into and out of the full-screen builder?

## Run it

Open `index.html` in a browser. There's no build step. Nothing is saved, and a reload resets everything.

Switch variants with the black bar at the bottom or the ← → keys (`?variant=A|B|C`). **Tasks to try** lists the same jobs for every variant. For testing, `?r=<route JSON>` deep-links a screen and `?b=<entry uuid>` opens the builder.

## Variants

All three use the same store: two channels, three locales, the built-in Page and Snippet types, the shipped Blog and FAQ, and an agency's own Store locations.

- **A · Sylius menu:** a Content section in the Sylius menu with one item per thing (Pages, Snippets, Blog, FAQ, Store locations, Slots, Media library, Presets, Transfer). Each is a Sylius grid. A row opens the builder, and everything about an entry lives there: settings, URL & SEO, publishing (revisions, share links), placements and problems. Slots have their own screen, where placements are ordered. Problems show per row, as menu badges, and in a panel on the Sylius dashboard (click the Sylius logo).
- **B · Content hub:** one Content item in the Sylius menu opens a hub with its own side navigation. Its home shows what needs attention, what's coming up, translation completeness and drafts in progress. Entries are one list across content types, filtered by type. A row opens the entry's overview page, which holds publishing, problems, placements, where it shows, revisions, share links, URLs and the homepage setting. The builder opens from there and returns there. There's no Slots screen: a capped slot is ordered in a dialog reached from a snippet's placements.
- **C · Store map:** one Store content item opens a map of one channel at a time: its homepage, its pages by URL, its collections by category, and its shop pages with their slots and placements. Problems are flagged on the tree and bubble up to the branches, with an "Only problems" filter. Selecting a node shows its panel: an entry's publishing and detail, or a slot's placements in order. A Library section holds all snippets, media, presets and transfer.

Every variant shares the create dialog (layout picker and starting points), the builder stub, the media library (folders, filters, "used in", replace, duplicate prompt, picker), presets, and transfer (export picker with dependencies, import dry run with conflicts).

## Verdict (2026-11-13)

**B · Content hub**, as built, with nothing borrowed from A or C. The decision and its detail are recorded on the ticket.

## Files

- `fixtures.js`: content types, entries with their states, slots, placements, media, presets, revisions and share links. It stands in for code-defined types and the server.
- `shared.js`: state, status and problem logic, the Sylius chrome, the shared screens and the builder stub.
- `variants.js`: the three information architectures.
- `main.js`: rendering, events, actions and the switcher bar.
