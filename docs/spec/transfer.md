# Transfer

Moving content between environments of a store: what a [transfer](../../GLOSSARY.md#transfer) moves, the [export](../../GLOSSARY.md#transfer) format, and what an [import](../../GLOSSARY.md#transfer) does.

- **Terms:** [transfer](../../GLOSSARY.md#transfer), [publishing](../../GLOSSARY.md#publishing), [entries](../../GLOSSARY.md#entries), [shop pages](../../GLOSSARY.md#shop-pages), [media](../../GLOSSARY.md#media).
- **Decisions:** [ADR 0016](../adr/0016-content-moves-between-environments-as-export-files-and-imports-land-in-drafts.md) (export files, imports land in drafts), [ADR 0002](../adr/0002-references-by-code-and-uuid.md) (references by code and UUID), [ADR 0009](../adr/0009-published-content-stays-in-the-rows-and-a-draft-is-json-beside-it.md) (the entry format), [ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md) (the preset format), [ADR 0013](../adr/0013-media-items-take-effect-on-save.md) (media items take effect on save), [ADR 0020](../adr/0020-extension-points-are-classes-whose-typed-properties-are-their-fields.md) (upgraders).

## What a transfer moves

- **What someone picks, plus what it needs.** An operator picks entries, editor presets or media folders, or everything. Each picked entry and editor preset brings along the snippets its block tree references and the media items it uses, as **dependencies** ([Content model](content-model.md#references)). Entries that are only linked to, or hand-picked in a list, aren't pulled in.
- **Published content.** An export takes each picked entry's published content, or its rows if it was never published. To move unfinished work, publish it in the source first.
- **On-save settings travel and apply on import:** `enabled`, the date window, the channels, the audience, "Homepage of", the position and a snippet's placements ([Content model](content-model.md#whats-drafted-and-what-takes-effect-on-save)). They apply as a save in the admin would.
  - A new entry still shows nothing until it's published, because a placement of an unpublished snippet shows nothing, and a homepage shoppers can't see falls back to Sylius's own.
  - A snippet that's already live in the target gets its imported placements at once, before its new content is published.
- **Media items** travel with their folders, metadata and original files. The target generates its own image variants. Missing folders are created, and existing folders aren't moved or renamed.
- **A transfer never deletes.** It creates and updates in the target. There's no mirror mode.

**What never travels:** drafts, revisions, share links and old slugs. The target records its own old slugs when a publish there changes a slug. The media usage index and the category reference table are rebuilt in the target from what's imported.

### Placements

An imported snippet's placements replace its placements in the target. Each placement goes right after the nearest placement before it in the source's slot order that the target also has (its `preceding` list), and otherwise first in the slot. That keeps a new placement from landing behind a capped slot's catch-all.

## Export format

An export is a folder, or a zip holding the same folder at its root. It's a contract: exports are kept, and committed as fixtures. All JSON follows the [canonical JSON](content-model.md#canonical-json) rules, so exports diff cleanly in Git.

```text
manifest.json
entries/<uuid>.json
presets/<uuid>.json
media/folders.json
media/<uuid>.json
media/<uuid>/<filename>
```

### manifest.json

```json
{
    "format": 1,
    "plugin": "2.0.0",
    "exportedAt": "2026-11-13T15:00:00Z",
    "picked": {
        "entries": ["0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e"],
        "presets": [],
        "media": []
    }
}
```

| Key | Value |
| --- | --- |
| `format` | The export format's version. An import refuses a format it doesn't know. |
| `plugin` | The plugin version that wrote the export, for information. |
| `exportedAt` | When the export was written, in UTC. |
| `picked` | The UUIDs of the picked records. Every other record in the export is a dependency. Picking a media folder picks every media item in it and its subfolders. |

### entries/&lt;uuid&gt;.json

```json
{
    "changedAt": "2026-11-12T10:15:00Z",
    "settings": {
        "enabled": true,
        "showFrom": null,
        "showUntil": null,
        "channels": ["FASHION_WEB"],
        "audience": {
            "mode": "everyone",
            "groups": []
        },
        "homepageOf": [],
        "position": null
    },
    "placements": [
        {
            "uuid": "0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e",
            "slot": "product.after_price",
            "scope": [
                {
                    "kind": "product_in_taxon",
                    "negate": false,
                    "params": { "includeChildren": true, "taxons": ["t_shirts"] }
                }
            ],
            "preceding": ["0193a5be-7301-7c2d-9e3f-4a5b6c7d8e9f"]
        }
    ],
    "entry": {}
}
```

| Key | Value |
| --- | --- |
| `changedAt` | When the entry's published content or an on-save setting last changed in the source ([Stale records](#stale-records)). |
| `settings.enabled` | `true` or `false`. |
| `settings.showFrom`, `settings.showUntil` | UTC date and time, or `null`. |
| `settings.channels` | Channel codes. |
| `settings.audience.mode` | `everyone`, `only` or `except`. |
| `settings.audience.groups` | Customer group codes, plus `@guest` and `@no_group` ([Content model](content-model.md#reserved-customer-group-values)). Empty for `everyone`. |
| `settings.homepageOf` | Channel codes. Empty for every content type but Page. |
| `settings.position` | An integer for a content type ordered by position, otherwise `null`. |
| `placements` | A snippet's placements, in no particular slot. Empty for every other content type. Each has its `uuid`, its `slot` identifier, its page `scope` as a list of [conditions](content-model.md#visibility-conditions), and its `preceding` placements in the source slot (nearest first). A placement has no `changedAt` of its own: changing one bumps its snippet's. |
| `entry` | The entry in the [entry format](content-model.md#entry-format). |

### presets/&lt;uuid&gt;.json

An editor preset, in the preset file format code presets use ([Layouts and presets](layouts-and-presets.md)), plus its `uuid` and `changedAt`:

```json
{
    "format": 1,
    "uuid": "0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a",
    "changedAt": "2026-11-10T08:00:00Z",
    "name": "Autumn hero",
    "category": "Campaigns",
    "description": null,
    "startingPoint": null,
    "blocks": []
}
```

Editor presets have no `thumbnail` and no `default`, which are for code presets only. `startingPoint` is `null` for a block preset. For a starting point it's `{"contentType", "layout"}`, where `layout` is the tree's [layout object](content-model.md#the-tree) or `null` ([Layouts and presets](layouts-and-presets.md#preset-file-format)). Editor presets keep their media references, so the media items they use are dependencies.

### media/folders.json

Every folder that holds an exported media item, with its parents:

```json
[
    { "uuid": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b", "name": "Campaigns", "parent": null },
    { "uuid": "0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c", "name": "Autumn", "parent": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b" }
]
```

Parents come before their children.

### media/&lt;uuid&gt;.json

```json
{
    "uuid": "0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60",
    "folder": "0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c",
    "kind": "image",
    "name": "Autumn banner",
    "filename": "autumn-banner.jpg",
    "mimeType": "image/jpeg",
    "size": 482113,
    "width": 2400,
    "height": 1000,
    "credit": null,
    "alt": {
        "en_US": "A woman in a red coat among autumn leaves"
    },
    "focalPoint": { "x": 0.42, "y": 0.35 },
    "poster": null,
    "checksum": "sha256:4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a",
    "changedAt": "2026-11-11T12:00:00Z"
}
```

| Key | Value |
| --- | --- |
| `folder` | The folder's UUID, or `null` for Unsorted. |
| `kind` | `image`, `video` or `document`. |
| `width`, `height` | Pixels, for images. `null` otherwise. |
| `alt` | A locale map, for images. Empty otherwise. |
| `focalPoint` | `x` and `y` from 0 to 1, for images. `null` otherwise. |
| `poster` | A video's poster, an image media item's UUID, or `null`. A poster is a dependency of its video. |
| `checksum` | `sha256:` plus the lowercase hex SHA-256 of the original file. |

The original file sits beside it at `media/<uuid>/<filename>`.

## Who runs it

- **The CLI.** `setono:cms:export` and `setono:cms:import` run in the foreground, for developers and deploys ([Commands](#commands)).
- **The admin.** Export downloads a zip. Import uploads one, shows the dry-run plan and asks for confirmation ([Admin](admin.md#transfer)). An import started in the admin runs on the Messenger worker that scheduled publishing already needs, and the admin shows its progress and result. Any admin user can import, because v2 has no editor roles.
  - **The uploaded zip is kept in the plugin's Flysystem storage** (`media.storage`), not in a temporary file, so a worker on another server can read it. It's deleted when the import finishes or is abandoned.
  - **A refused import is a result, not an error.** The handler records the refusal and its reasons for the admin and returns, so Messenger doesn't retry it. Only an unexpected error throws.
- **The Sylius fixture** `setono_cms_import` loads an export folder, then publishes everything it imported, for demo and test data ([Extending](extending.md#fixture)).

## What an import does

1. **It reads, checks and upgrades.** It checks the zip and every media file ([Media files](#media-files)). Content type, layout and block type upgraders run on the export's entries and presets as they're read ([Content model](content-model.md#schema-changes)).
2. **It plans.** It works out what's created, updated, unchanged or left alone, the conflicts, the homepage changes and the unresolved references. A record identical to the target's is marked unchanged first, before the [stale check](#stale-records), so it's never a conflict. With `--publish`, the plan also runs publish validation on every entry it would publish. A dry run shows the plan and stops.
3. **It applies the plan all or nothing**, in one database transaction. Any conflict or error means nothing changes. Files are written to storage only after the plan is complete, and the delayed delete of a replaced file is dispatched after the transaction commits ([Media](media.md#replacing-a-file)).

What applying does to each record:

- **A picked entry** has its content saved as its draft, or written to its rows when it was never published in the target, as a save in the builder would ([Publishing](publishing.md)). It goes live only through a publish: the `--publish` option, or an editor in the target. That publish validates, checks slug clashes, records old slugs and stores a revision like any other. With `--publish`, a publish that fails validation fails the whole import.
- **A picked editor preset** is replaced, or created.
- **A picked media item** replaces its file the way a replace in the library does: it keeps its UUID and takes effect everywhere at once ([ADR 0013](../adr/0013-media-items-take-effect-on-save.md)). Its metadata is updated too.
- **A dependency** (a snippet or media item that came along) is created when the target lacks it, and otherwise left as it is, so the target's own edits to a shared snippet, or a logo replaced there, survive.
- **On-save settings and placements** of the records it writes apply at once.
- **An unchanged record**, identical to the target's, is skipped: no draft and no revision.
- **Every write an import makes sets `changedAt` to the export's value**: the draft or rows it saves, the on-save settings and placements it applies, and the publish that `--publish` or the fixture runs. A record keeps it until someone changes it in the target ([Stale records](#stale-records)).

When it's done, the import dispatches `ImportFinished` ([Extending](extending.md#events)).

### Media files

An export is untrusted input: any admin user can import one. So every file in it goes through the same checks as an upload ([Media](media.md#uploading)), whether its media item is picked, a dependency, or new in the target:

- **The zip.** Every entry must resolve inside the export's root. An absolute path, a `..` segment or a link refuses the import.
- **The filename.** `filename` must be a plain basename: no `/`, `\`, `..` or control characters. Anything else refuses the import.
- **The checksum.** The file's SHA-256 must match `checksum`. A mismatch refuses the import.
- **The file itself.** Its type is checked against `media.types` and its size against the upload limit. Its MIME type, size and dimensions are read from the file, and the values in the JSON are ignored. SVG is sanitised.

A file that fails a check refuses the whole import, with the media item named.

## Conflicts

The plan lists conflicts, and the import refuses while there are any. Records match by UUID only.

| Conflict | How it's resolved |
| --- | --- |
| A picked entry has a draft or a scheduled publish in the target. | `--overwrite`, or the admin's checkbox, discards the draft and cancels the schedule. |
| A picked record changed in the target after the export's copy ([Stale records](#stale-records)). | `--overwrite`, or the admin's checkbox. |
| An entry's URL clashes with another entry's in a channel and locale. | An editor changes a slug in one environment. `--overwrite` doesn't resolve it. |

Overwriting a picked entry keeps the target's previous published content restorable as a revision once the import is published.

### Stale records

Every entry, media item and editor preset keeps a `changedAt`: when its published content, its file or metadata, or one of its on-save settings (including placements) last changed. Draft saves don't bump it. An export records each record's `changedAt`.

A picked record whose `changedAt` in the target is later than in the export is a conflict. Picking everything from an older copy of production would otherwise roll back production's newer media files and on-save settings at once, and neither keeps a history.

Every write an import makes, including its publish, sets the source's `changedAt`, and the record keeps it until someone changes it in the target, so a later export of the same record isn't mistaken for a stale one. A record identical to the target's is never stale, because the unchanged check runs first. The check relies on the environments' clocks roughly agreeing.

An entry created separately in each environment has two UUIDs, so it's two entries. Environments cloned from each other share UUIDs.

## References that don't resolve

References that don't resolve in the target import unchanged: Sylius codes the target lacks (products, taxons, channels, customer groups, locales, product attributes) and CMS UUIDs that are neither exported nor present. The dry run and the import's result list them, and the admin flags them as broken references ([Admin](admin.md#problems)).

## Code the target must have

The target's code must know everything the export uses:

- **A content type, layout or block type the target doesn't know** makes the import refuse. A version newer than the target's code refuses too. Deploy the code first.
- **Older versions** are upgraded on import.
- **A layout or slot the target's code no longer declares** is kept as it is and flagged, as everywhere else ([Admin](admin.md#problems)).
- **A disabled content type, block type or layout** imports like any other. Disabling only hides it from editors.
- **The Embed code block is the exception.** While `embed_code` is off, an import refuses an `embed_code` block that isn't already in the entry's published tree in the target, with the same key ([ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md), [Blocks](blocks.md#embed-code)).

## Commands

The commands are listed in [Extending](extending.md#console-commands).

```text
setono:cms:export <dir> [--entry=UUID]... [--content-type=CODE]... [--media-folder=UUID]... [--presets] [--all] [--zip]
```

- **Selection.** `--entry` picks entries, `--content-type` picks every entry of a content type, `--media-folder` picks every media item in a folder and its subfolders, and `--presets` picks every editor preset. `--all` picks every entry, editor preset and media item. Options combine, and at least one is required.
- **Output.** It writes the folder to `<dir>`, which must be empty or missing. `--zip` writes `<dir>.zip` instead.

```text
setono:cms:import <path> [--dry-run] [--publish] [--overwrite]
```

- `<path>` is an export folder or zip.
- `--dry-run` prints the plan and changes nothing. It checks everything the real run would, so it exits with the code the real run would, and works as a gate in CI and deploys.
- `--publish` publishes every imported entry as part of the import.
- `--overwrite` overwrites the records the plan lists as conflicts, discarding their drafts. It doesn't resolve URL clashes.
- **Exit codes**, with the reasons printed:

  | Code | Meaning |
  | --- | --- |
  | `0` | It applied, or the dry run would. |
  | `1` | It refused for a reason `--overwrite` doesn't fix: unknown code, a URL clash, a failed publish, a file that fails its checks, or an `embed_code` block while it's off. |
  | `2` (`Command::INVALID`) | `<path>` isn't a readable export folder or zip, or its `format` is unknown. |
  | `3` | It refused only because of conflicts that `--overwrite` would resolve. |

  Unresolved references are reported and don't fail the import.
