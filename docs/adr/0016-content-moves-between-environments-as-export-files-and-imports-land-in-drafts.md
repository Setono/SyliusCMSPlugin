# Content moves between environments as export files, and imports land in drafts

Content moves from one environment to another, such as from staging to production, as files. An export is a folder, or a zip of it, that holds:

- each entry as one JSON document, in the format drafts and revisions use (ADR 0009), together with its enabled switch, date window, channels, audience, placements and "Homepage of" channels
- editor presets, in the preset file format (ADR 0012)
- media items, with their metadata and original files

Console commands and the admin both export and import, and the same folder loads as a Sylius fixture. Records match by UUID only (ADR 0002).

An export takes the published content of the entries someone picked, plus the snippets their block trees reference and the media items they use. Entries that are only linked to, or hand-picked in a list, aren't pulled in. An import never deletes anything. It saves each entry's content as the entry's draft, so imported content goes live only through a publish, either with an import option or by an editor in the target. That publish validates, records old slugs, stores a revision and expires caches like any other. What takes effect on save (the enabled switch, the date window, channels, the audience, placements and homepage settings) applies as soon as the import saves.

Picked records follow "source wins", unless the target's copy is newer. Snippets and media items that come along only as dependencies are created when the target lacks them, and otherwise left as they are. An import first builds a plan, which a dry run shows, and then applies it all or nothing. Three things are conflicts:

- a picked entry with a draft or a scheduled publish in the target
- a picked record that changed in the target after the time the export records for it, such as a replaced file, moved placements, a page switched off or newer published content
- a URL that clashes with another entry

A conflict stops the import until the operator chooses to overwrite those records, which discards their drafts, or fixes the clash. This matters because picking everything from an older copy of production would otherwise roll back production's newer media files and on-save settings at once, and neither keeps a history.

We chose this because files work with no network route between environments, and the format already exists for drafts, revisions and presets. Going through the draft means there's still only one way to go live, so an import gets publishing's checks, its revisions and its cache expiry for free.

## Considered Options

- **Pulling over HTTP from a configured source environment**, as `wagtail-transfer` does. There are no files to carry, but each environment needs credentials for the other and a network route to it. Staging behind a VPN or basic auth often has no such route.
- **Mirroring the whole CMS**, deleting whatever the source lacks. It's easy to reason about for a launch, but it wipes whatever editors changed in the target.
- **Writing imports straight to the published rows.** It's faster, but it's a second way to go live that has to repeat everything a publish does.
- **Updating dependencies too.** The target would match the source, but importing one page would overwrite production's own edits to a shared snippet, or a logo replaced there.
- **Sylius fixture YAML as the format.** It would be a second way of writing down a block tree.
- **Only adding missing media items when everything is picked.** It protects files, but a stale import could still roll back placements and settings.

## Consequences

- The draft's JSON format becomes a contract, like the stored block-tree format (ADR 0012), because exports are kept and committed as fixtures. Upgraders run on imported files. A content type or block type the target doesn't know, or a block-type version newer than its code, makes the import refuse: deploy the code first.
- Every field type must declare the references it holds, and whether each referenced record is a dependency or only a link, so the exporter can find them.
- Drafts, revisions, share links and old slugs never travel. The target records its own old slugs when a publish there changes a slug.
- A snippet that's already live in the target gets its imported placements at once, before its new content is published.
- References that don't resolve in the target import unchanged, and the import reports them. The admin flags them as broken references, as ADR 0002 accepts for a deleted product.
- An import started in the admin runs on the Messenger worker that scheduled publishing already needs.
- Without a shared UUID, an entry created separately in each environment is two entries. Environments cloned from each other share UUIDs.
- Every record keeps the time it last changed, including its on-save settings, and an export records that time. Imported content keeps the source's time until someone changes it in the target, so a later export of the same record isn't mistaken for a stale one. The check relies on the environments' clocks roughly agreeing.
