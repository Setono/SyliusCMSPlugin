# Placements take effect on save

A placement shows a snippet in a slot, limited by a page scope and ordered by a position. A placement takes effect as soon as it's saved and doesn't go through drafts or publishing, even though snippets do.

When the content shows is controlled on the snippet: a placement of an unpublished snippet shows nothing, and the snippet's scheduling and date window decide when it appears. Placements only decide where.

We chose this over making placements part of the snippet's published state. Reordering a slot touches the placements of several snippets. If placements were versioned with their snippet, one drag in a slot would mean drafting and republishing every snippet involved.

The cost is that changing the page scope of a live placement, such as widening it from one taxon to all products, takes effect immediately. The editor has to rely on the preview rather than a draft.
