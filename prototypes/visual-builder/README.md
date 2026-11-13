# PROTOTYPE: visual builder (throwaway, never merge)

Built for [Visual builder look and behaviour](https://github.com/Setono/SyliusCMSPlugin/issues/195), part of the map [Map: Sylius CMS Plugin v2 spec](https://github.com/Setono/SyliusCMSPlugin/issues/186). It lives on the throwaway `prototype/visual-builder` branch as a primary source and is never merged.

## Question

How should the visual builder look and behave for an editor composing an entry, such as a page, a snippet or a menu?

## Run it

Open `index.html` in a browser. There's no build step. Nothing is saved, and a reload resets everything.

Switch variants with the black bar at the bottom or the ← → keys (`?variant=A|B|C`). Pick what to edit from the bar's dropdown (`?entry=…`). **{ } JSON** shows the stored entry.

## Variants

- **A · Three panes:** a full-screen builder. The block tree is on the left, the live preview in the centre and the selected block's fields on the right.
- **B · Canvas first:** a full-screen builder where the preview is the editor. It has an on-block toolbar, text typed in place and **+** between blocks. Fields open in a floating drawer, and the tree is an optional Layers panel.
- **C · Form in the admin:** stays inside the Sylius admin. Blocks are nested cards with their fields inside, and the preview is a side panel you can hide.

All three share the same data, preview and widgets: the locale and channel switchers, completeness counts and outdated flags, the "All languages" badge, the source picker, bindings, snippet references with detach, and presets.

## Verdict (2026-11-13)

**A · Three panes**, with these borrowed:

- From B: typing text in place in the preview, **+** between blocks in the preview, and the toolbar on the selected block.
- From C: menu links get an editable label box right in A's tree row, with the target shown beside it, so a list of links can be relabelled without selecting each one.

The decision and its detail are recorded on the ticket.

## Files

- `fixtures.js`: block types, content types, sources, bindable properties, slots and sample content. It stands in for code-defined types and the server.
- `core.js`: state, tree operations, translation logic and the shared widgets.
- `preview.js`: a fake Sylius shop page that renders the draft, with click-to-select.
- `variants.js`: the three shells.
- `main.js`: rendering, events, actions, drag and drop, modals and the switcher bar.
