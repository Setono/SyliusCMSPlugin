# Media

The media library: what it holds, how editors organise and upload media items, their metadata, how changes reach live pages, and how images are processed and rendered.

- **Terms:** [media](../../GLOSSARY.md#media): media library, media item, focal point.
- **Decisions:** [ADR 0013](../adr/0013-media-items-take-effect-on-save.md) (media items take effect on save and have no revisions), [ADR 0014](../adr/0014-images-are-processed-by-liip-behind-an-image-provider.md) (images are processed by LiipImagine behind our own image provider), [ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md) (exported code presets leave media fields empty), [ADR 0002](../adr/0002-references-by-code-and-uuid.md) (content refers to media items by UUID), [ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md) (nothing rendered is cached in v2.0).

## What the library holds

A media item is one of three kinds:

| Kind | Formats | Stored |
| --- | --- | --- |
| `image` | JPEG, PNG, GIF, WebP, AVIF and SVG | SVG is sanitised on upload. The others are stored as uploaded, after [EXIF handling](#exif). |
| `video` | MP4 and WebM | as uploaded, with no transcoding |
| `document` | a configurable allowlist, PDF by default | as uploaded |

- The allowed MIME types per kind and the maximum upload size are config: `media.types` and `media.max_upload_size` ([Extending](extending.md#configuration)).
- **The upload limit** defaults to PHP's effective limit (`UploadedFile::getMaxFilesize()`, the smaller of `upload_max_filesize` and `post_max_size`). A configured `media.max_upload_size` above it is lowered to it, because PHP refuses a bigger file before the plugin sees it. The builder gets the effective limit with its registry and checks a file before uploading it.
- **YouTube and Vimeo videos aren't media items.** The Video block holds their URL in a field ([Blocks](blocks.md)).
- **Product and taxon images stay out.** They remain Sylius's own. Content shows them through block types that read products or taxons, such as product cards and taxon tiles. There are no image bindings in v2.0.

**Media items are shared by every channel and are public.** They have no channels and no audience, and anyone with a file's URL can fetch it. The content that uses a media item decides where it shows. Private files are out of scope.

## Folders

- Folders nest. Each media item sits in exactly one folder, or in none, which the library shows as Unsorted.
- **Folders exist only in the library.** Files sit in storage under paths built from the media item's UUID, so moving a media item or renaming a folder never moves a file or changes a URL.
- There are no tags in v2.0.

## Search and filters

- **Search** covers the name, the original filename and the alt text in every locale.
- **Filters:** kind (image, video, document), unused (no use in the [usage index](#usage-index)) and missing alt text (images without alt text in one or more of the store's locales).

## Uploading

- Editors upload in the library and in the builder's media picker, which is the same library opened as a picker ([Admin](admin.md#media-library)).
- They drag and drop many files at once, and each goes into the open folder.
- **Duplicates.** When an uploaded file's content matches an existing media item's current file, the editor is offered that media item instead. They can still upload a copy, which becomes a new media item.
- A file over the upload limit, or of a type that `media.types` doesn't allow, is refused with a message naming the limit.
- **Every file is checked the same way**, whether it's uploaded, replaced or imported ([Transfer](transfer.md#media-files)): its type against `media.types`, its size against the upload limit, its MIME type, dimensions and checksum read from the file itself, and SVG sanitised. Nothing is trusted from the file's name or from metadata that came with it.

### EXIF

On upload, the plugin:

1. applies the EXIF orientation to the image
2. reads its dimensions
3. strips embedded metadata from the stored original, so location and device data never leak

## Metadata

| What | Kinds | Notes |
| --- | --- | --- |
| Name | all | Defaults to the original filename without its extension. |
| Original filename | all | Used in the [permanent URL](#permanent-urls). |
| Dimensions | images, videos | Read on upload. |
| MIME type and size | all | |
| Credit | all | A free-text credit or copyright line. |
| Alt text | images | Translatable, one value per locale. It falls back through the [fallback chain](../../GLOSSARY.md#entries) ([Localization](localization.md)). |
| Focal point | images | One point, as `x` and `y` from 0 to 1, with 0.5 and 0.5 by default. Every crop keeps it in view. There's no per-use crop in v2.0. |
| Poster | videos | Another image media item, shown before the video plays. |

- **Missing alt text is flagged, not required**, so bulk uploads aren't blocked. The library grid and the media item show the flag, and the overview counts it as a flag, not as a problem ([Admin](admin.md#problems)). There's no "decorative" switch.
- **Alt text can be overridden for one use.** A block type that shows an image gives it a translatable Text field for the override, such as the Image block's alt override ([Blocks](blocks.md)). An empty override falls back to the media item's alt text.
- **Captions are block fields**, because a caption depends on where the image is used.

## Changes take effect on save

Media items aren't entries, so they have no drafts or revisions ([ADR 0013](../adr/0013-media-items-take-effect-on-save.md)). Saving a media item takes effect at once wherever it's used: editing its name, alt text, credit or poster, moving its focal point, and replacing its file.

### Replacing a file

- **The UUID stays.** Content that refers to the media item shows the new file without being edited or published again.
- **The editor sees the "used in" list first.** The replace dialog lists every use and says that the change takes effect at once. Alt text and the focal point are kept.
- **A replaced file is checked like an upload**, and must be of the same kind.
- **New storage paths.** A replaced file gets a new storage path, and so does every image variant generated from it. Moving the focal point gives every variant a new URL too. Caches and CDNs therefore never serve an old file under the current URL.
- **Old files are deleted later.** A delayed Messenger message on Sylius's `main` transport deletes the old file and its variants after `media.grace_period`, one day by default, so pages cached before the replace don't show broken images in the meantime. The message is dispatched after the database transaction commits, so a replace that rolls back never deletes the file still in use.

### Events

| Change | Event |
| --- | --- |
| Saving metadata or moving the focal point | `MediaItemSaved` |
| Replacing the file | `MediaItemReplaced` |
| Deleting | `BeforeMediaItemDelete`, then `MediaItemDeleted` |

They're described in [Extending](extending.md#events). v2.0 caches nothing for them to expire. They carry that duty to any cache a project or a later version adds ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)).

## Permanent URLs

Documents and videos have a permanent URL, so links outside the shop, such as in a newsletter or on a printed QR code, survive a replace:

```
/media/{uuid}/{filename}
```

- The route is `setono_sylius_cms_media` ([Extending](extending.md#routes)). It sits outside the locale prefix and is the same in every channel, because media items are shared and public. `{uuid}` must be a UUID, so the route never catches other files under `/media`, such as Sylius's `/media/image` and Liip's `/media/cache`.
- Only the UUID is looked up. The filename is cosmetic: the plugin generates it from the original filename, and any filename answers.
- It answers with a `302` temporary redirect to the current file, because a replace changes the target. An unknown UUID answers 404.
- Images have no permanent URL, because templates always render their current path.
- The `media` link target links to a document or video by its permanent URL ([Extending](extending.md#link-targets)).

## Usage index

Because references are UUIDs inside JSON, a usage index records which media items each save uses ([ADR 0013](../adr/0013-media-items-take-effect-on-save.md)).

- **It covers** each entry's draft and its published content, and editor presets, in both fields and rich text. Field type handlers list the references a value holds ([Extending](extending.md#field-types)), and the index keeps the ones of type `media_item`.
- **It's updated** on every draft save, publish, preset save and import, and when an entry or preset is deleted.
- **Revisions don't count.** Restoring a revision whose media item was deleted gives a broken reference, which the admin flags ([ADR 0002](../adr/0002-references-by-code-and-uuid.md)).
- **Code presets don't count.** Exported code presets leave media fields empty ([ADR 0012](../adr/0012-code-presets-are-json-files-in-the-stored-block-tree-format.md)).

The index drives:

- each media item's **Used in** list, with a link to each entry or preset that uses it
- the usage count in the library grid
- the **unused** filter
- deletion: a media item can't be deleted while the index lists a use

### Deleting

- Delete is disabled while the media item is in use, and the admin says where it's used.
- When nothing uses it, `BeforeMediaItemDelete` is dispatched first. A listener can veto the delete with a message, which the admin shows. Otherwise the media item, its file and its variants are deleted, and `MediaItemDeleted` is dispatched.

## Image processing

- **Storage.** Media items are stored in the plugin's own Flysystem storage, `media.storage`, which is a local public directory by default: `%kernel.project_dir%/public/media/cms`. Like Sylius's own `public/media/image`, it holds the only copy of every file, so a project must keep it across deploys, such as on a shared or persistent volume. Gaufrette isn't supported, and Sylius's `sylius.storage` isn't reused ([ADR 0014](../adr/0014-images-are-processed-by-liip-behind-an-image-provider.md)).
- **LiipImagine** generates image variants on first request, with the plugin's own loader and resolver, both named `setono_sylius_cms` by default (`media.liip.loader` and `media.liip.resolver`). It caches them in the plugin's storage.
- **Cropping.** A variant with an aspect ratio that differs from the source is cropped around the media item's focal point.
- **Formats.** Variants are output as WebP, and as AVIF where the server's image library supports it. SVG and animated GIF are served as uploaded, at every width.
- **No storage calls when building URLs.** The default image provider builds variant URLs itself and lets Liip's resolve step generate what's missing, so rendering a `srcset` never costs a remote call per entry, even on S3.
- **Moving to S3** or similar means configuring both the plugin's Flysystem storage and its Liip loader and resolver.
- **The image provider.** Templates never call Liip. The [image helper](#the-image-helper) asks `ImageProviderInterface` for each variant's URL ([Extending](extending.md#images)). A project can swap in an image CDN by aliasing the interface, and the Sylius 2 port can add a Symfony UX Image adapter.

The first visitor to each variant waits for it to be generated.

## The image helper

`cms_image(media, options = {})` renders an image media item. Its rendering is modelled on Symfony UX Image, which can't run on Sylius 1.14 ([ADR 0014](../adr/0014-images-are-processed-by-liip-behind-an-image-provider.md)).

`media` is a `MediaItemInterface`, a media item's UUID, or `null`. It prints nothing for `null`, a broken reference or a media item that isn't an image. In the builder's preview and the preset browser, an empty image renders a neutral grey box at the image's aspect ratio instead ([Blocks](blocks.md)).

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `layout` | `'fixed'`, `'constrained'` or `'full_width'` | `'constrained'` | **Fixed** renders at `width` and offers 1x and 2x. **Constrained** renders up to `width` and shrinks with its container. **Full width** spans the viewport. |
| `width` | `int\|null` | the source width | The rendered width in CSS pixels. Required for `fixed`. |
| `aspect_ratio` | `float\|null` | the source's | Width divided by height. Variants are cropped to it around the focal point. |
| `sizes` | `string\|null` | generated from `layout` and `width` | Overrides the generated `sizes` attribute. |
| `priority` | `bool` | `false` | `true` loads the image eagerly with `fetchpriority="high"`, for an image above the fold. |
| `alt` | `string\|null` | the media item's alt text | Overrides the alt text. Templates pass the block's alt override here. |
| `class` | `string\|null` | `null` | Added to the `<img>`. |
| `format` | `'auto'`, `'webp'` or `'original'` | `'auto'` | **Auto** renders a `<picture>` with an AVIF `<source>` where supported and WebP in the `<img>`. **WebP** renders a plain `<img>`. **Original** keeps the source format. |

What it renders:

- **A width ladder.** The `srcset` takes the widths in `media.widths` that the layout needs, capped at the source's width, so it never upscales.
- **`sizes`** is generated from the layout and width unless the template overrides it: `(min-width: {width}px) {width}px, 100vw` for `constrained`, `100vw` for `full_width`, and none for `fixed`, which uses `1x`/`2x` descriptors.
- **`<img>` or `<picture>`.** `<picture>` is used for AVIF, because Liip doesn't negotiate formats.
- **Lazy loading by default**, `loading="lazy"` and `decoding="async"`, unless `priority` is set.
- **`width` and `height` attributes**, from the rendered width and the aspect ratio, so the image doesn't shift the layout as it loads.
- **The alt attribute** is always printed, empty when there's no alt text.
- **URLs are built without storage calls**, through the image provider.
