# Symfony UX Image: responsive images for the media library

Research for [#201](https://github.com/Setono/SyliusCMSPlugin/issues/201) (Media library), part of the v2 spec map [#186](https://github.com/Setono/SyliusCMSPlugin/issues/186).
Researched 2026-11-13. Sources were read at `symfony/ux-image` **v3.7.0** (2026-10-31, the newest release) and the `symfony/ux` monorepo `3.x` branch at `a232e0c` (2026-11-11; `src/Image/src` is unchanged since v3.7.0).
Other sources: Packagist metadata, the symfony.com docs, symfony/ux issues and PRs, LiipImagineBundle **2.18.2**, Sylius `v1.14.21` and `v2.3.2` `composer.json`, and the provider docs.
Each claim links to the file or page that owns it. My own conclusions are marked **(inference)**.

## Short answer

**UX Image cannot be installed on Sylius 1.14, and no version line could be.** It exists only in the UX 3.x line, and every release requires PHP ≥ 8.4 and Symfony `^7.4|^8.0`. Sylius 1.14 caps Symfony at 6.4. A Composer dry run of `sylius/sylius ~1.14.21` with `symfony/ux-image` fails on `symfony/config`, even with PHP 8.4 (§2).

**It is also not an image processor.** It never reads, decodes, resizes or stores an image. It turns `(path, width, height, fit, format, quality)` into URLs for an external, URL-based image service: Cloudflare, Cloudinary, imgix or KeyCDN, or a self-hosted IPX (Node) or Imagor server. It then renders the `<img>`/`<picture>` markup around those URLs. Its maintainers dropped a local PHP processor (Glide) from the initial PR and later closed a community Glide bridge (§4).

The plugin's settled requirements are local Flysystem storage by default, a focal point, on-demand generation cached in storage, and WebP/AVIF. Against those, UX Image covers the markup half and none of the processing half. **Recommendation: keep LiipImagine as the processor, and borrow UX Image's rendering design**: layouts, the ladder, `sizes`, `<img>` versus `<picture>`, and side-effect-free URL generation. Put a small provider seam in our own code, so the Sylius 2 port can add a UX Image or CDN adapter later (§Comparison).

---

## 1. What it is, who maintains it, maturity

| | |
|---|---|
| Package | `symfony/ux-image`, type `symfony-bundle`, MIT, "Responsive images for Symfony, delivered through URL-based image providers" ([Packagist](https://packagist.org/packages/symfony/ux-image), [composer.json](https://github.com/symfony/ux/blob/v3.7.0/src/Image/composer.json)) |
| Status | **EXPERIMENTAL**: "currently experimental and is likely to change, or even change drastically" ([README](https://github.com/symfony/ux/blob/v3.7.0/src/Image/README.md), [docs](https://symfony.com/bundles/ux-image/current/index.html)) |
| Releases | v3.6.0 (tagged 2026-10-12, published 2026-10-13) and v3.7.0 (2026-10-31, published 2026-11-04). No other tags ([Packagist p2 metadata](https://repo.packagist.org/p2/symfony/ux-image.json)). [CHANGELOG](https://github.com/symfony/ux/blob/v3.7.0/src/Image/CHANGELOG.md): 3.6.0 "Add the component", 3.7.0 "Add `Dsn::getPort()`" |
| Maintained branches | `3.x` only ([.symfony.bundle.yaml](https://github.com/symfony/ux-image/blob/v3.7.0/.symfony.bundle.yaml)) |
| Author / owner | Hugo Alliaume (`Kocal`, a symfony/ux maintainer) wrote the RFC and the merged implementation. He has been the CODEOWNER of `src/Image` since 2026-10-31 ([#4178](https://github.com/symfony/ux/pull/4178)). Simon André and Kevin Bond reviewed and approved it ([#3838 reviews](https://github.com/symfony/ux/pull/3838)) |
| Age at time of research | About 6 weeks since the first merge (2026-09-28), with 2 releases |

### How it came about

- Issue [#2903](https://github.com/symfony/ux/issues/2903) (2025-07) asked for a `<twig:img>` "inspired by `<NuxtImg>`". Four PRs then tried to build it, and all four were closed:
  - [#3188](https://github.com/symfony/ux/pull/3188) targeted 2.x, had a focal point, and made `liip/imagine-bundle` a hard requirement.
  - [#3549](https://github.com/symfony/ux/pull/3549) introduced a provider abstraction.
  - [#3765](https://github.com/symfony/ux/pull/3765) only validated hand-written markup.
  - [#3768](https://github.com/symfony/ux/pull/3768) (+20k lines) had a GD/Intervention pipeline, Flysystem storage and persisted `ImageAsset`s.
- The RFC [#3834](https://github.com/symfony/ux/issues/3834) (2026-09-05) set the scope: *"Storing the file is not our job. The application owns the upload … and hands us a path. UX Image turns that path plus a requested size into URLs, and renders the markup around them. Nothing else."* and *"The value of UX Image is the providers, not the pipeline."* It follows Nuxt Image's provider design and ports its layout logic from [unpic](https://unpic.pics/).
- [#3838](https://github.com/symfony/ux/pull/3838) merged on 2026-09-28 with the Cloudflare and KeyCDN bridges. Later PRs added presets ([#3937](https://github.com/symfony/ux/pull/3937)), the KeyCDN secure token ([#3945](https://github.com/symfony/ux/pull/3945)), IPX ([#4091](https://github.com/symfony/ux/pull/4091)) and Imagor ([#4151](https://github.com/symfony/ux/pull/4151)). Cloudinary and imgix also exist as bridges ([#3939](https://github.com/symfony/ux/issues/3939), [#3940](https://github.com/symfony/ux/issues/3940)).
- The Glide (local PHP) bridge was dropped from #3838 and rejected again in [#4092](https://github.com/symfony/ux/pull/4092) (see §4).

**Maturity (inference):** brand new, experimental, and outside Symfony's BC promise. The maintainers explicitly reserve the right to "change drastically". The API surface is small, though, and well tested: a snapshot-based test case ships for provider authors ([`Test/RendererSnapshotTestCase.php`](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Test/RendererSnapshotTestCase.php)).

## 2. Version constraints. Can it run on Sylius 1.14?

`symfony/ux-image` v3.6.0 and v3.7.0 both require ([Packagist](https://repo.packagist.org/p2/symfony/ux-image.json)):

| Dependency | Constraint |
|---|---|
| `php` | `>=8.4` |
| `symfony/config`, `dependency-injection`, `http-kernel`, `options-resolver` | `^7.4\|^8.0` |
| Twig API (optional) | `symfony/ux-twig-component` + `symfony/twig-bundle` (the bundle only registers the Twig functions and components when both are present: [UXImageBundle.php L124](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/UXImageBundle.php#L124)). `ux-twig-component` 3.x itself needs PHP ≥ 8.4, Symfony `^7.4\|^8.0`, `twig/html-extra ^3.29` ([Packagist](https://packagist.org/packages/symfony/ux-twig-component)) |
| Each provider bridge | `php >=8.4`, `symfony/ux-image ^3.6` or `^3.7` (`ux-cloudflare-image`, `-cloudinary-`, `-imgix-`, `-keycdn-`, `-ipx-`, `-imagor-image` on Packagist) |

The docs say the same: "The package supports PHP 8.4 or later and Symfony 7.4 or 8.x" ([doc/index.rst](https://github.com/symfony/ux/blob/v3.7.0/src/Image/doc/index.rst)).

**Sylius 1.14 requires PHP `^8.1` and Symfony `^5.4.21 || ^6.4`** ([composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json)). Composer confirms the conflict. A dry run with `sylius/sylius ~1.14.21` + `symfony/ux-image *` on platform PHP 8.4.20 fails:

```
sylius/sylius v1.14.21 requires symfony/config ^5.4.21 || ^6.4
symfony/ux-image[v3.6.0, ..., v3.7.0] require symfony/config ^7.4|^8.0
```

On PHP 8.3 it also fails earlier, on `php >=8.4`.

**No version line could run on 1.14.** The `2.x` branch of `symfony/ux` has no `src/Image` at all: its packages are Autocomplete, Chartjs, Cropperjs, Dropzone, Icons, LazyImage, LiveComponent, Map, Native, Notify, React, StimulusBundle, Svelte, Swup, TogglePassword, Toolkit, Translator, Turbo, TwigComponent, Typed and Vue (GitHub contents API, `ref=2.x`). UX 3.0 shipped on 2026-04-09, and "the `2.x` branch is no longer maintained" (Kocal on [#3188](https://github.com/symfony/ux/pull/3188#issuecomment-4243605326)). The only 2.x image package, `symfony/ux-lazy-image`, does something else (BlurHash placeholders plus lazy loading), and Packagist marks it **abandoned** (last release v2.36.0, 2026-05-06; [Packagist](https://packagist.org/packages/symfony/ux-lazy-image)).

**Sylius 2.3** requires PHP `^8.3` and Symfony `^6.4.1 || ^7.4 || ^8.0`, and accepts UX `^2.36 || ^3.0` ([v2.3.2 composer.json](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json)). A dry run of `sylius/sylius ~2.3.2` + `symfony/ux-image ^3.7` + `symfony/ux-twig-component ^3.7` + `liip/imagine-bundle ^2.18` on PHP 8.4 resolves (Symfony 7.4.23, ux-image 3.7.0, liip 2.18.2). **(inference)** A hard dependency would therefore limit a Sylius 2 version of the plugin to PHP 8.4+ and Symfony 7.4+ installs, a subset of what Sylius 2.3 supports.

## 3. Declaring and rendering variants

Sources: [doc/index.rst](https://github.com/symfony/ux/blob/v3.7.0/src/Image/doc/index.rst), [ImageRenderer.php](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/ImageRenderer.php), [LayoutResolver.php](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/LayoutResolver.php), [RenderOptions.php](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/RenderOptions.php), [ImageMarkup.php](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Twig/ImageMarkup.php).

**API.** Three ways in, with the same options:

```twig
<twig:ux:image src="/uploads/hero.jpg" alt="Hero" width="800" height="450" />
<twig:ux:picture src="/uploads/hero.jpg" alt="Hero" width="800" height="450" />
{{ ux_image('/uploads/hero.jpg', 'Hero', {width: 800}, {class: 'rounded'}) }}
{{ ux_picture(...) }}
{{ ux_image_url('/uploads/hero.jpg', {width: 1200, height: 630}) }}   {# one URL, e.g. og:image #}
```

In PHP the same work is done by `ImageUrlGenerator::generate()` and `ImageRendererInterface::render()/renderPicture()`. Props are `src`, `alt`, `layout`, `width`, `height`, `fit`, `format`, `quality`, `priority`, `object-fit`, `breakpoints`, `operations` and `preset`. Any other attribute (`class`, `data-*`, `sizes`, `style`) passes through to the `<img>`. A caller `sizes` replaces the generated one, and a caller `style` merges with it.

**Layouts drive widths and `sizes`.** The logic is ported from unpic ([LayoutResolver L59-L91](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/LayoutResolver.php#L59-L91)):

| `layout` | `srcset` candidates | generated `sizes` | requires |
|---|---|---|---|
| `fixed` | `[width, 2×width]` | `{width}px` | `width` |
| `constrained` (default) | `width`, `2×width`, plus every ladder entry below `2×width` | `(min-width: {width}px) {width}px, 100vw` | `width` |
| `full-width` | the whole ladder | `100vw` | `height` |

- The default ladder ("resolutions") is `6016, 5120, 4480, 3840, 3200, 2560, 2048, 1920, 1668, 1280, 1080, 960, 828, 750, 640`. You can configure it globally (`ux_image.resolutions`) or per image (`breakpoints`).
- Candidates use `w` descriptors.
- The renderer does not know the source's intrinsic size, so it does not cap the ladder at it. A review on #3838 flagged this ("produces 11 candidates up to 2400x1300, even when the original is smaller"). The released code still builds candidates from `width`/`2×width` and the ladder only.

**Aspect ratio.** There is no ratio prop. Passing both `width` and `height` implies the ratio. Each candidate then gets `height = round(candidate × height/width)`, and `fit` defaults to `cover`, so every variant is cropped to that ratio ([ImageRenderer L138-L149](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/ImageRenderer.php#L138-L149), [RenderOptions](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/RenderOptions.php)). `fit` is `cover` or `contain` (the [`Fit`](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Fit.php) enum).

**Formats (WebP/AVIF).** `ux_image.formats` (default `['avif', 'webp', 'jpeg']`) is intersected with what the active provider can encode:

- `<twig:ux:picture>` renders one `<source type="image/…">` per surviving format, then an `<img>` fallback in the last format.
- `<twig:ux:image>` renders one `<img>`. Providers that negotiate from `Accept` (Cloudflare `format=auto`, Cloudinary `f_auto`, imgix `auto=format`, IPX `f_auto`) get a single `auto` URL. Otherwise the `<img>` uses the last surviving format.
- A `format` prop pins one format.

The caller chooses the element, never the provider. Switching provider does not change the markup, a point raised in review.

**Presets.** These are named bundles of `width`/`height`/`fit`/`format`/`quality`/`operations` in config, used with `preset="thumbnail"`. Layout, breakpoints and priority stay in the template ([#3937](https://github.com/symfony/ux/pull/3937), docs "The presets option").

**Art direction** (a different crop per media query) is "out of scope for this version" (docs, "`<img>` or `<picture>`").

## 4. How it processes images

- **It doesn't.** "The package never decodes, resizes or encodes an image itself. Every transformation is expressed as a URL, built by whichever provider is active" ([doc/index.rst L12-L15](https://github.com/symfony/ux/blob/v3.7.0/src/Image/doc/index.rst#L12-L15)). A provider is about 60 lines that map the tuple to a URL ([`ProviderInterface`](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Provider/ProviderInterface.php): `generateUrl(ImageTransformation)`, `getSupportedOperations()`, `getSupportedFormats()`, `supportsAutoFormat()`). One review sums it up: "generating a URL never makes a network call" ([#3838 review](https://github.com/symfony/ux/pull/3838#pullrequestreview-5329853341)).
- **Providers shipped:** Cloudflare Image Resizing, Cloudinary, imgix and KeyCDN (SaaS/CDN), plus IPX (self-hosted, built on Node and sharp; the docs run it as a Compose service) and Imagor (a self-hosted image server).
- **Default provider `null://null`:** "transforms nothing: each image renders with its original URL, no `srcset` and no `sizes`, but keeps its dimensions and layout `style`" ([doc/index.rst L278-L280](https://github.com/symfony/ux/blob/v3.7.0/src/Image/doc/index.rst#L278-L280), [NullProvider](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Provider/NullProvider.php)).
- **Glide, Liip and Imagine are not used.** The RFC planned Glide as the local provider. The #3838 review recommended dropping it: it pulls in `league/glide`, `intervention/image`, `intervention/gif`, `league/flysystem`, `league/flysystem-local` and GD, "Cached variants are still streamed through PHP on every request", "replacing an original keeps serving the old variants", and "most of the bugs I found are in this bridge". The reviewer concluded: "I would rather have no local transformation than a heavy one that is only half configurable." A community Glide bridge ([#4092](https://github.com/symfony/ux/pull/4092)) was closed the same day, with IPX as the suggested self-hosted alternative. The earlier Liip-based attempt (#3188) was closed in favour of the provider design.
- **On demand or pregenerated:** always on demand, at the provider, on the first request for a URL. Nothing runs at upload time. The RFC calls this a feature: "changing a breakpoint changes a URL instead of triggering a regeneration batch".
- **Abuse protection:** there is no generic signing in the core. The RFC: "the key handling differs enough that it probably deserves its own contract". It is per provider (docs, "Protecting transformations"):
  - Cloudflare: only the zone's own origin.
  - Cloudinary: Strict transformations + `api_secret`, and restricted fetch domains.
  - imgix: secure URLs + `sign_key`.
  - KeyCDN: Secure Token + `secure_token`.
  - Imagor: `sign_key`, with unsafe mode off.
  - IPX: **no signing**, only `IPX_ALLOWED_MODIFIERS`.

  The core does reject `.`/`..` path segments ([ImageTransformation L38-L42](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/ImageTransformation.php#L38-L42)).
- **Caching:** entirely the provider's or CDN's job. The docs warn that each distinct URL "is one more image the provider generates and caches". IPX "doesn't cache results", so put a reverse proxy or CDN in front of it. Its default `Cache-Control` is `max-age=60` (docs, IPX section). Imagor has its own "result storage" ([Imagor docs](https://docs.imagor.net/storage/)).

## 5. Cropping: focal point, gravity, smart crop

- **The core has no focal point, gravity or smart crop.** [`ImageTransformation`](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/ImageTransformation.php) carries only `path, width, height, fit, format, quality, operations`, and `Fit` is `cover|contain`. Cover crops are the provider's default (Imagor: "Cover uses the default centered crop", docs Imagor section).
- **A focal point can only go through `operations`, keyed by provider name.** The docs make that keying deliberate: "a flat, un-keyed `gravity` option would silently vanish the moment the application switched from Cloudflare to another provider". Unknown operations or provider keys throw ([ImageUrlGenerator](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/ImageUrlGenerator.php)). Per provider, a normalised focal point (x, y ∈ 0..1) maps like this:

| Provider | Operation the bridge forwards | Focal-point form |
|---|---|---|
| Cloudflare | `gravity` | `XxY`, decimals 0.0–1.0 ([Cloudflare docs](https://developers.cloudflare.com/images/transform-images/transform-via-url/)); also `auto`, `face` |
| imgix | `crop`, `fp-x`, `fp-y`, `fp-z` | `crop=focalpoint` + `fp-x`/`fp-y` 0.0–1.0, needs `fit=crop`, which the bridge emits for `cover` ([imgix fp-x](https://docs.imgix.com/en-US/apis/rendering/focalpoint-crop/fp-x), [ImgixProvider](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Bridge/Imgix/src/ImgixProvider.php)) |
| Imagor | `focal` | `focal(X,Y)` or a region, floats 0–1 accepted ([Imagor filters](https://docs.imagor.net/filters/)) |
| KeyCDN | `crop` | `crop=fp,{x},{y}` with ratios 0–1; also `crop=smart` ([KeyCDN docs](https://www.keycdn.com/support/image-processing)) |
| Cloudinary | `g`, `x`, `y` | gravity plus coordinates; `g_auto` picks "the most interesting regions" ([Cloudinary reference](https://cloudinary.com/documentation/transformation_reference)) |
| IPX | `position`, `extract` | `position` takes only named positions, gravities or strategies, not coordinates ([IPX README](https://github.com/unjs/ipx)) |

**(inference)** So yes, a focal point stored on our `Media` entity *could* drive the crop. The plugin would pass, for example, `operations: {cloudflare: {gravity: '0.31x0.62'}, imgix: {crop: 'focalpoint', 'fp-x': 0.31, 'fp-y': 0.62}, …}` on every render, and keep its own mapping table per provider. UX Image does not abstract this. IPX would need an exact `extract` box computed from the stored source dimensions, because named gravities would lose precision.

## 6. Storage (Flysystem, S3)

- **No Flysystem, and no storage access at all.** `src` is "the public URL path of the original". Providers fetch it from your origin (Cloudflare, Cloudinary fetch delivery, imgix Web Folder, Imagor `origin`, KeyCDN pull zone), read it from a mounted directory (IPX `IPX_FS_DIR`), or use it as a key in their own storage (docs, "Providers"). The RFC excluded "storage backends, write sessions, Doctrine types and regeneration commands". The Flysystem-based PR #3768 was closed.
- **S3 sources work only through the provider:**
  - imgix: "With a storage source, like Amazon S3, `src` is the path of the image in the bucket" (docs, imgix).
  - Imagor: has S3/GCS/filesystem storage of its own ([Imagor storage](https://docs.imagor.net/storage/)).
  - Cloudinary: has upload delivery and auto-upload mapping.
  - Otherwise the S3 bucket must be reachable over HTTP from the provider.
- **(inference)** For this plugin, every Flysystem path would first need a public URL, either from a public bucket or CDN or from a controller that streams the original. Only then could a provider fetch it. Nothing in UX Image would read or write our Flysystem storage.

## 7. Placeholders, lazy loading, layout shift

- **No placeholders.** There is no blurhash, LQIP or dominant colour anywhere in `src/` (code search). The old `ux-lazy-image` that did BlurHash is abandoned (§2).
- **Lazy by default.** Every image gets `loading="lazy"`. `priority` switches to `loading="eager" fetchpriority="high"` ([ImageRenderer L102-L118](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/ImageRenderer.php#L102-L118)). The docs warn to set `priority` on LCP images. No `decoding` attribute is emitted.
- **Layout shift:** it emits `width`/`height` attributes (the *requested* dimensions, not the source's). It also emits an inline `style`, such as `object-fit: cover; max-width: …px; max-height: …px; aspect-ratio: W / H; width: 100%; height: auto` for `constrained` ([LayoutResolver L96-L134](https://github.com/symfony/ux/blob/v3.7.0/src/Image/src/Renderer/LayoutResolver.php#L96-L134)). **(inference)** Inline `style` attributes need `style-src-attr 'unsafe-inline'` (or an equivalent) on shops with a strict CSP.

## 8. Stimulus, AssetMapper, JavaScript

None. The package has no `assets/` directory and no `package.json`. It is server-side PHP and Twig only (file listing of the v3.7.0 split). It needs `symfony/ux-twig-component` only for the Twig functions and components, and the PHP services work without it. **(inference)** Nothing would have to be added to Sylius's Encore builds.

## 9. Cache busting

"A transformation URL only depends on the original's path and on the transformation parameters. When you replace an original under the same path, the provider and the browsers keep serving the variants they already cached. Give each version of an image its own path instead" ([doc/index.rst L233-L240](https://github.com/symfony/ux/blob/v3.7.0/src/Image/doc/index.rst#L233-L240)). This matches the plugin's settled rule that a replaced file gets a new storage path.

## 10. Extension points

- **Custom provider:** implement `ProviderInterface`. Build it from a DSN with a `ProviderFactoryInterface`; `AbstractProviderFactory` gives scheme matching and DSN options through OptionsResolver. Tag the factory `ux_image.provider_factory` with a `provider` attribute, which serves as both the DSN scheme and the `operations` key (docs, "Writing your own provider"). This is the hook for an image CDN, and also for a self-hosted endpoint such as a Liip runtime-filter URL **(inference)**.
- **One provider per application.** `ux_image.provider` is a single service built from one DSN (`ux_image.provider: '%env(resolve:UX_IMAGE_DSN)%'`) ([config/services.php](https://github.com/symfony/ux/blob/v3.7.0/src/Image/config/services.php)). **(inference)** A plugin that relied on the global service would hand its image pipeline to whatever DSN the host app chose. A plugin could instead build its own `ImageRenderer` (public constructor: provider, `LayoutResolver`, formats, …), but the class is `final` and experimental.
- Other public pieces: `ImageUrlGenerator` (autowirable), the `ImageRendererInterface` alias (decoratable), presets, `RendererSnapshotTestCase`. The Twig layer (`ImageRuntime`, `ImageMarkup`, components) is `@internal`.

---

## Comparison with the LiipImagine plan

The plan: templates ask for an aspect ratio plus a `sizes` hint, a Twig helper renders `srcset` from a configured ladder, variants are generated on first request around the focal point and cached in storage, and output is WebP/AVIF.

| | Symfony UX Image | LiipImagineBundle plan |
|---|---|---|
| Installs on Sylius 1.14 | **No** (PHP ≥ 8.4, Symfony ≥ 7.4) | **Yes.** Sylius 1.14 already requires `^2.10`. 2.18.2 needs PHP `^8.1`, Symfony `^5.4\|…\|^8.0` ([Packagist](https://packagist.org/packages/liip/imagine-bundle), [Sylius composer.json](https://github.com/Sylius/Sylius/blob/v1.14.21/composer.json)) |
| Installs on Sylius 2.3 | Yes, on PHP 8.4 + Symfony 7.4/8 only | Yes. Sylius 2.3 requires `^2.17` ([composer.json](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json)) |
| Who transforms | External service (CDN/SaaS, or IPX/Imagor sidecar) | PHP in the app (GD/Imagick/Vips via Imagine) |
| Works out of the box, no CDN | Markup only. `null://null` serves the original with no `srcset` | Yes |
| Reads our Flysystem/S3 | No. Needs a public URL to the original | Yes: `FlysystemV2Loader` + `FlysystemV2Resolver` on `FilesystemOperator` (Flysystem 2/3) ([Binary/Loader](https://github.com/liip/LiipImagineBundle/tree/2.18.2/Binary/Loader), [Resolver](https://github.com/liip/LiipImagineBundle/tree/2.18.2/Imagine/Cache/Resolver)) |
| Focal point | Per-provider `operations`. We map it ourselves for each provider | No built-in focal filter. We compute a `crop` box from the focal point and ratio and pass it as a runtime config, or write a custom filter loader **(inference)** |
| Responsive markup | Built in: layouts, ladder, `sizes`, `<img>`/`<picture>`, width/height, lazy | None. We write the Twig helper |
| WebP/AVIF | Per provider. `<picture>` sources for providers that don't negotiate | A filter set's `format` option sets the output format, which the Imagine driver encodes; the default is the source's format ([FilterManager L201](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Imagine/Filter/FilterManager.php#L201)). Per-format quality keys (`webp_quality`, `avif_quality`, …) are forwarded for the Vips driver ([L28-L36](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Imagine/Filter/FilterManager.php#L28-L36), [L176-L185](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Imagine/Filter/FilterManager.php#L176-L185)). Only WebP has built-in `Accept` negotiation (`liip_imagine.webp.generate`, [ImagineController L217-L220](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Controller/ImagineController.php#L217-L220)). AVIF would need `<picture>` sources **(inference)** |
| Abuse protection | Provider signing, where the provider has it | Runtime-config URLs are HMAC-signed (`/media/cache/resolve/{filter}/rc/{hash}/{path}`, checked in [ImagineController L132](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Controller/ImagineController.php#L132)). Named filter sets limit the variant space |
| Cost of building a URL | Pure string work, no I/O | `getBrowserPath()` calls `isStored()` per variant ([CacheManager L118-L130](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Imagine/Cache/CacheManager.php#L118-L130)), and the Flysystem resolver turns that into `fileExists()` ([FlysystemV2Resolver L88-L91](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Imagine/Cache/Resolver/FlysystemV2Resolver.php#L88-L91)). On S3 that is a remote call per `srcset` entry. With `webp.generate` on, it always returns the resolve-controller URL. `PsrCacheResolver`/`CacheResolver` can cache resolution |
| Pregenerate on upload | Not applicable | Possible: `WarmupCache` Messenger message ([Message/WarmupCache.php](https://github.com/liip/LiipImagineBundle/blob/2.18.2/Message/WarmupCache.php)) |
| Placeholders | None | None |
| Maturity | Experimental, about 6 weeks old | Mature, already in every Sylius install |

### Could we depend on it?

**Not for v2 on Sylius 1.14:** it cannot be installed (§2). Even where it can be installed, it would not meet the settled requirements on its own: it has no local processing, never reads Flysystem, and has no focal-point concept. A plugin that requires it would also force every shop to buy or run an image service just to get a `srcset`.

### Should we borrow its design?

Yes. These parts carry over to a Liip-backed pipeline directly **(inference)**:

1. **A provider seam.** Have a small interface in our plugin, along the lines of `generateUrl(path, width, height, fit, format, quality, focalPoint)`, with a Liip-backed default. This keeps "what the template asks for" separate from "who transforms". It mirrors `ProviderInterface` but adds the focal point as a first-class, normalised `(x, y)`, which UX Image left to per-provider operations.
2. **Layouts instead of hand-written width lists.** Use `fixed`, `constrained` (default) and `full-width`, ladder-derived candidates, generated `sizes` with a caller override, and `w` descriptors. Improve on UX Image by capping candidates at the stored intrinsic width: we know it, UX Image doesn't. That also caps the number of variants generated per image.
3. **The caller picks `<img>` or `<picture>`.** Use `<picture>` with one `<source type>` per format for AVIF+WebP. Liip only negotiates WebP, and `Vary: Accept` is fragile behind CDNs, which the #3838 review also noted.
4. **Lazy by default, plus an explicit `priority` flag** (`eager` + `fetchpriority="high"`) for the hero or LCP image. Always emit `width`/`height` for the requested ratio.
5. **URL generation without I/O.** Don't call Liip's `getBrowserPath()` (one `fileExists` per candidate). Emit deterministic signed URLs to the resolve/runtime controller and let the first request generate and redirect. Alternatively, record "variant generated" state in our own media table or a PSR cache.
6. **Presets as named bundles** of width, ratio, fit, quality and format, in plugin config, referenced from block-type templates.
7. **Versioned paths for cache busting.** This is already settled for the plugin, and UX Image documents the same rule.

### What it means for the Sylius 2 port

- On Sylius 2.3 with PHP 8.4 and Symfony 7.4+, UX Image *can* be installed next to Liip (dry run in §2). It would still be experimental and global to the app (one DSN). **(inference)** The cleanest way to use it then is an **optional adapter**: when the host app has `symfony/ux-image` installed with a real provider configured, our provider seam delegates URL generation to `ImageUrlGenerator`. The focal point is mapped into `operations` for the known providers (table in §5). Liip stays the default and the fallback. Nothing in v2's templates or data model needs to change for that if the seam above exists from the start.
- A hard dependency in the Sylius 2 version would drop Sylius 2.3 shops on PHP 8.3 or Symfony 6.4 (§2) **(inference)**.
- If UX Image later grows a local transformer or a focal-point abstraction, re-evaluate. The maintainers deferred both: "Revisit local processing in a follow-up", and art direction "can wait".

## Recommendation

Keep **LiipImagineBundle** as the v2 image pipeline, and model the rendering API on UX Image's (§"Should we borrow its design?"). Concretely:

- **Templates** call one helper, for example `cms_image(media, {layout, ratio, width, sizes?, priority?})`. It renders `<img>` or `<picture>` with ladder-derived, intrinsic-capped `srcset`, `sizes`, `width`/`height` and `loading`.
- **URLs** come from our own provider interface. The default implementation emits signed Liip runtime-config URLs, with the crop box computed from the stored focal point and source dimensions. It does no I/O.
- **Variants** are generated on first request through the Flysystem loader and resolver, stored in the plugin's storage (local or S3), and output as AVIF/WebP/JPEG via `<picture>`.

Trade-offs:

- **Pro:** this runs on 1.14 today. It reuses a dependency every Sylius install already has, keeps files in our Flysystem storage, and handles the focal point exactly. It needs no third-party service.
- **Con:** image processing runs in PHP workers. The first visitor to each variant pays the generation latency; Messenger warmup on upload can soften this. AVIF depends on the server's Imagick/libvips build **(inference)**. We own roughly the 300 lines of layout and markup code that UX Image would otherwise provide **(inference from the size of LayoutResolver + ImageRenderer)**.
- **Port path:** the provider seam lets the Sylius 2 version add a UX Image/CDN adapter without template or schema changes.

## Not verified

- No code was run in a real application. Behaviour comes from the v3.7.0 source, its docs and Composer dry runs.
- Liip's AVIF output was not tested. It depends on the Imagine driver and the server's codec build.
- Cloudinary's exact coordinate semantics for `g`/`x`/`y` were not confirmed. The fetched reference page did not cover `g_xy_center`.
