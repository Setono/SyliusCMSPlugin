<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Media\AltTextResolverInterface;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageFormatsInterface;
use Setono\SyliusCMSPlugin\Media\ImageLayout;
use Setono\SyliusCMSPlugin\Media\ImageOptions;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageRendition;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_image(): renders an image media item responsively, modelled on Symfony UX Image. Variant URLs come from the image
 * provider, so rendering never calls the storage. See "The image helper" in docs/spec/media.md
 */
final readonly class ImageRuntime implements RuntimeExtensionInterface
{
    /**
     * @param list<int<1, max>> $widths media.widths, in ascending order
     */
    public function __construct(
        private ImageProviderInterface $imageProvider,
        private ImageFormatsInterface $imageFormats,
        private AltTextResolverInterface $altTextResolver,
        private MediaItemArguments $mediaItems,
        private RenderContexts $renderContexts,
        private array $widths,
    ) {
    }

    /**
     * Prints nothing for null, a broken reference or a media item that isn't an image. In the builder's preview, it
     * prints a grey placeholder at the image's aspect ratio instead. An <img>, or a <picture> when it has <source>s, for
     * AVIF or art-directed images, or a picture class
     *
     * @param array<string, mixed> $context
     * @param MediaItemInterface|string|null $media a media item, or its UUID
     * @param array<array-key, mixed> $options
     */
    public function image(array $context, MediaItemInterface|string|null $media, array $options = []): string
    {
        $options = ImageOptions::fromArray($options);

        $mediaItem = $this->mediaItems->mediaItem($media, MediaKind::Image);
        if (null === $mediaItem) {
            return RenderContext::PREVIEW_MODE_BUILDER === $this->renderContexts->of($context)->previewMode ? self::placeholder($options) : '';
        }

        $format = match ($options->format) {
            ImageOptions::FORMAT_ORIGINAL => ImageFormat::Original,
            ImageOptions::FORMAT_WEBP => ImageFormat::Webp,
            default => $this->imageFormats->supports(ImageFormat::Webp) ? ImageFormat::Webp : ImageFormat::Original,
        };
        // Liip doesn't negotiate formats, so the browser picks AVIF from a source where it supports it
        $avif = ImageOptions::FORMAT_AUTO === $options->format && $this->imageFormats->supports(ImageFormat::Avif);

        // The art-directed images come first, so the browser takes one where its media query matches
        $sources = '';
        foreach ($options->artDirection as $artDirectedImage) {
            $artDirectedItem = $this->mediaItems->mediaItem($artDirectedImage->image, MediaKind::Image);
            if (null !== $artDirectedItem) {
                $sources .= self::sources($this->rendition($artDirectedItem, $options, $artDirectedImage->aspectRatio), $artDirectedImage->media, $format, $avif);
            }
        }

        $image = $this->rendition($mediaItem, $options, $options->aspectRatio);
        if ($avif && $image->hasVariants()) {
            $sources .= HtmlTag::open('source', ['type' => ImageFormat::Avif->mimeType(), 'srcset' => $image->srcset(ImageFormat::Avif), 'sizes' => $image->sizes]);
        }

        $img = HtmlTag::open('img', [
            'src' => $image->src($format),
            'srcset' => $image->hasVariants() ? $image->srcset($format) : null,
            'sizes' => $image->sizes,
            'width' => $image->width,
            'height' => $image->height,
            'alt' => $this->alt($context, $mediaItem, $options),
            'class' => $options->class,
            'loading' => $options->priority ? null : 'lazy',
            'decoding' => $options->priority ? null : 'async',
            'fetchpriority' => $options->priority ? 'high' : null,
        ]);

        if ('' === $sources && null === $options->pictureClass) {
            return $img;
        }

        return HtmlTag::open('picture', ['class' => $options->pictureClass]) . $sources . $img . '</picture>';
    }

    private function rendition(MediaItemInterface $mediaItem, ImageOptions $options, ?float $aspectRatio): ImageRendition
    {
        return ImageRendition::of($this->imageProvider, $mediaItem, $options, $aspectRatio, $this->widths);
    }

    /**
     * The <source>s of an art-directed image for its media query: AVIF first where it's offered, then the <img>'s format.
     * They carry the image's own size, which may differ from the <img>'s
     */
    private static function sources(ImageRendition $image, string $media, ImageFormat $format, bool $avif): string
    {
        $size = ['sizes' => $image->sizes, 'width' => $image->width, 'height' => $image->height];
        $sources = '';
        if ($avif && $image->hasVariants()) {
            $sources .= HtmlTag::open('source', ['media' => $media, 'type' => ImageFormat::Avif->mimeType(), 'srcset' => $image->srcset(ImageFormat::Avif)] + $size);
        }

        return $sources . HtmlTag::open('source', ['media' => $media, 'srcset' => $image->srcset($format)] + $size);
    }

    /**
     * The template's alt text, unless it's empty, or else the media item's in the locale through the fallback chain, or
     * else none: the attribute is always printed
     *
     * @param array<string, mixed> $context
     */
    private function alt(array $context, MediaItemInterface $mediaItem, ImageOptions $options): string
    {
        if (null !== $options->alt && '' !== trim($options->alt)) {
            return $options->alt;
        }

        return $this->altTextResolver->resolve($mediaItem, $this->renderContexts->of($context)->localeCode) ?? '';
    }

    /**
     * A grey box at the image's aspect ratio, the one the template gives or 16:9, as wide as the image renders. It stands in
     * for the <picture> and the <img>, so it takes both their classes
     */
    private static function placeholder(ImageOptions $options): string
    {
        $size = null === $options->width ? '' : match ($options->layout) {
            ImageLayout::Fixed => sprintf('; width: %dpx', $options->width),
            ImageLayout::Constrained => sprintf('; max-width: %dpx', $options->width),
            ImageLayout::FullWidth => '',
        };

        return ImagePlaceholder::html($options->aspectRatio ?? ImagePlaceholder::ASPECT_RATIO, [$options->pictureClass, $options->class], $size);
    }
}
