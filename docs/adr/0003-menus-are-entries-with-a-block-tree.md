# Menus are entries with a block tree

**Deferred.** Menus don't ship in v2.0. They were ruled out of scope on 2026-11-13 in [Menus](https://github.com/Setono/SyliusCMSPlugin/issues/200). This ADR records the shape they'll take when they're added later, as one more built-in content type, with no remodeling.

A menu is an entry of the built-in Menu content type, and its items are blocks: link and group block types, plus any other block type inside mega menus.

We chose this over a separate menu-item model because menus need what blocks already have:

- nesting
- visibility conditions on each item
- references to entities that survive slug changes
- publishing
- transfer between environments

A dedicated model would have to rebuild each of these.

The cost is that menus are edited in the block builder, so the builder has to handle a deep tree of small link blocks well.
