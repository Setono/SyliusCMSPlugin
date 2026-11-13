# Layouts are interchangeable frames, and their fields are stored with the block tree

Only content types whose entries have their own URL have layouts, and each layout belongs to one content type. A layout sets no rules on blocks. The content type decides which block types the top level of its tree may hold, so every layout of a content type accepts any tree of that type, and switching layout never touches the tree. A layout can declare fields of its own, such as "Hide the title" or "Transparent header". Their values are stored in the entry's block tree JSON, not as columns, so they're drafted, translated, upgraded and transferred the same way block fields are. When an editor switches layout, values carry over to fields of the new layout with the same name and type, and the rest are dropped.

We chose this because agencies want presentation options on each page, and those options differ from layout to layout. Making a layout for every combination of options multiplies layouts. Columns on the content type need a migration each, and a Page subclass just to add a toggle that one layout uses. Letting layouts narrow the tree's top level would mean switching layout could leave the tree invalid.

## Considered Options

- **A layout per combination of options**, such as "Full width" and "Full width, no title". This is the simplest model, but the number of layouts multiplies with every option.
- **Layout options as entry fields**, stored as columns on the content type. These can be queried, but each one needs a migration, and they apply to every layout of the type.
- **Layouts that narrow the tree's top level**, such as a Landing layout that allows heroes when an Article layout doesn't. The cost is that switching layout could leave the tree invalid.

## Consequences

- Rule of thumb: a field that every layout of a content type uses, or that gets queried, belongs to the content type as a column. A presentation option that one layout uses belongs to that layout.
- Layout field values can't be filtered or sorted on in SQL.
- Changing a layout's fields needs the same versioning and upgraders as changing a block type's fields.
- Snippets, menus and collection entries without a URL have no layout. Their block tree renders with no frame wherever it's shown. A snippet that needs a frame uses a wrapper block.
- When a layout is removed from code, its entries render with the content type's default layout and are flagged in the admin.
