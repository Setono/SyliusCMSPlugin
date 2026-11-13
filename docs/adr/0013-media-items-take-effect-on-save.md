# Media items take effect on save and have no versions

Media items aren't entries, so they have no drafts or revisions. Saving a media item takes effect at once in every place that uses it: replacing its file, editing its alt text, or moving its focal point. Replacing a file keeps the media item's UUID, so content that refers to it (ADR 0002) shows the new file without being edited or published again. The editor sees the item's "used in" list before replacing, so they know what the change reaches.

We chose this because the common reasons to replace a file, such as a new logo, a corrected size guide or a fresher banner photo, should reach every page at once. Placements work the same way (ADR 0005).

## Considered Options

- **Immutable media items**, where replacing uploads a new item. Changes would reach live pages only through a publish, but swapping a logo means editing and publishing every entry that uses it.
- **Media versions that wait for each entry's publish.** Every entry would keep showing the version it was published with. That means tracking a media version per reference, and the picker, preview, share links and revisions would all have to agree on which version to show.

## Consequences

- A replaced file gets a new storage path, and so does every variant generated from it, as does any variant when the focal point moves. Caches and CDNs therefore never serve the old file under the current URL. The old file and its variants are deleted by a delayed message after a configurable grace period, so pages cached before the replace don't show broken images in the meantime.
- Documents and videos get a permanent URL that redirects to the current file, so links outside the shop, such as in a newsletter or on a printed QR code, survive a replace. Images don't need one, because templates always render their current path.
- Saving a media item has to expire cached output that uses it, like a publish does.
- Because references are UUIDs in JSON, a usage index records which media items each entry draft, published entry and editor preset uses, in fields and in rich text. It drives the "used in" list and the "unused" filter, and a media item can't be deleted while the index lists a use. Revisions aren't indexed, so restoring a revision whose media item was deleted gives a broken reference.
- Revisions can't show the file a page had when it was published, only the media item's current file.
