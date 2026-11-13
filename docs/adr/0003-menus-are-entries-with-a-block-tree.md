# Menus are entries with a block tree

A menu is an entry of the built-in Menu content type, and its items are blocks: link and group block types, plus any other block type inside mega menus.

We chose this over a separate menu-item model because menus need what blocks already have:

- nesting
- visibility conditions on each item
- references to entities that survive slug changes
- publishing
- transfer between environments

A dedicated model would have to rebuild each of these.

The cost is that menus are edited in the block builder, so the builder has to handle a deep tree of small link blocks well.
