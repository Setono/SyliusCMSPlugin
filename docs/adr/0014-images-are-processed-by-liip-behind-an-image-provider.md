# Images are processed by LiipImagine behind our own image provider

Media items are stored in the plugin's own Flysystem storage, a local public directory by default, with its own LiipImagine loader and resolver names. LiipImagine generates image variants on first request, cropped around the media item's focal point, and caches them in that storage as WebP, or as AVIF where the server supports it. SVG and animated GIF are served as uploaded.

Templates don't call Liip. They call our own Twig helper, which asks an image provider interface for variant URLs. The interface takes the focal point as normalised coordinates. The helper's rendering is modelled on Symfony UX Image: a layout (fixed, constrained or full width), a width ladder capped at the source width, generated `sizes` that a template can override, `<img>` or `<picture>` (using `<picture>` for AVIF, which Liip doesn't negotiate), lazy loading by default with a priority flag, `width` and `height` to prevent layout shift, and URLs built without storage calls.

We chose this because LiipImagine is already in every Sylius install and runs on 1.14 today, and our own storage keeps CMS files apart from product images, under names that don't change in Sylius 2.0. The provider interface keeps the door open for an image CDN.

## Considered Options

- **Symfony UX Image.** It only exists in UX 3.x (PHP 8.4, Symfony 7.4 or later), so it can't be installed on Sylius 1.14. It also doesn't process images: it builds URLs for an external image service (Cloudflare, Cloudinary, imgix, or a self-hosted IPX or Imagor) and renders the markup. Its rendering design is what we copy. See the [research](https://github.com/Setono/SyliusCMSPlugin/blob/research/ux-image/docs/research/ux-image.md).
- **An external image service only.** It takes processing out of PHP, but a default local install wouldn't work without one.
- **league/glide.** It's flexible on demand, but it would be a second image stack beside Sylius's Liip, and Sylius developers know it less well.
- **Generating every variant on upload** with Messenger. The first view is never slow, but every new or changed format means regenerating the whole library.
- **Reusing Sylius's `sylius.storage` and Liip's `default` loader and resolver.** That's less configuration, but CMS files would mix with product images, and Sylius 2.0 renames those Liip names. Gaufrette was ruled out because Sylius 2.3 drops it.

## Consequences

- Images are processed in PHP, and the first visitor to each variant waits for it to be generated.
- Liip checks whether a variant exists when it builds a URL, which costs a remote call per `srcset` entry on S3. The provider has to avoid that, for example by building URLs itself and letting Liip's resolve controller generate what's missing.
- Moving to S3 or similar means configuring both the plugin's Flysystem storage and its Liip loader and resolver.
- The Sylius 2 port can add a UX Image adapter behind the provider interface, but not as a hard dependency, because that would drop Sylius 2.3 shops on PHP 8.3 or Symfony 6.4.
