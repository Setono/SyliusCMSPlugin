<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Field\MediaItemReferences;
use Setono\SyliusCMSPlugin\Media\AltTextResolverInterface;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageLayout;
use Setono\SyliusCMSPlugin\Media\ImageOptions;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\ImageVariants;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_image(): renders an image media item responsively, modelled on Symfony UX Image. Variant URLs come from the image
 * provider, so rendering never calls the storage. See "The image helper" in docs/spec/media.md
 */
final readonly class ImageRuntime implements RuntimeExtensionInterface
{
    /** The aspect ratio of the placeholder of an empty image the template gives none for */
    private const PLACEHOLDER_ASPECT_RATIO = 16 / 9;

    /**
     * @param list<int<1, max>> $widths media.widths, in ascending order
     */
    public function __construct(
        private ImageProviderInterface $imageProvider,
        private AltTextResolverInterface $altTextResolver,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private RenderContextFactoryInterface $renderContextFactory,
        private array $widths,
    ) {
    }

    /**
     * Prints nothing for null, a broken reference or a media item that isn't an image. In the builder's preview, it
     * prints a grey placeholder at the image's aspect ratio instead
     *
     * @param array<string, mixed> $context
     * @param MediaItemInterface|string|null $media a media item, or its UUID
     * @param array<array-key, mixed> $options
     */
    public function image(array $context, MediaItemInterface|string|null $media, array $options = []): string
    {
        $options = ImageOptions::fromArray($options);

        $mediaItem = $this->mediaItem($media);
        if (null === $mediaItem) {
            return RenderContext::PREVIEW_MODE_BUILDER === $this->renderContext($context)->previewMode ? self::placeholder($options) : '';
        }

        $sourceWidth = $mediaItem->getWidth();
        $sourceHeight = $mediaItem->getHeight();
        $aspectRatio = $options->aspectRatio ?? (null !== $sourceWidth && null !== $sourceHeight && $sourceHeight > 0 ? $sourceWidth / $sourceHeight : null);
        $width = $options->width ?? $sourceWidth;

        $attributes = [
            'width' => $width,
            'height' => null === $width || null === $aspectRatio ? null : max(1, (int) round($width / $aspectRatio)),
            'alt' => $this->alt($context, $mediaItem, $options),
            'class' => $options->class,
            'loading' => $options->priority ? null : 'lazy',
            'decoding' => $options->priority ? null : 'async',
            'fetchpriority' => $options->priority ? 'high' : null,
        ];

        if (!ImageVariants::areMadeFor($mediaItem) || null === $width || null === $sourceWidth || null === $sourceHeight) {
            // Served as uploaded at every width, so the transform asks for nothing in particular
            $src = $this->imageProvider->url($mediaItem, new ImageTransform(max(1, $width ?? 1), null, ImageFormat::Original, $mediaItem->getFocalPoint()));

            return self::tag('img', ['src' => $src] + $attributes);
        }

        // The widest a variant can be without upscaling: the source's width, or its crop's at another aspect ratio
        $maxWidth = null === $options->aspectRatio ? $sourceWidth : max(1, min($sourceWidth, (int) floor($sourceHeight * $options->aspectRatio)));
        $widths = $options->layout->widths(max(1, $width), max(1, $maxWidth), $this->widths);
        $sizes = ImageLayout::Fixed === $options->layout ? null : ($options->sizes ?? $options->layout->sizes($width));

        $url = fn (int $variantWidth, ImageFormat $format): string => $this->imageProvider->url($mediaItem, new ImageTransform($variantWidth, $options->aspectRatio, $format, $mediaItem->getFocalPoint()));
        $srcset = static function (ImageFormat $format) use ($url, $widths, $options): string {
            $candidates = [];
            foreach ($widths as $i => $variantWidth) {
                $candidates[] = $url($variantWidth, $format) . ' ' . (ImageLayout::Fixed === $options->layout ? ($i + 1) . 'x' : $variantWidth . 'w');
            }

            return implode(', ', $candidates);
        };

        $format = match ($options->format) {
            ImageOptions::FORMAT_ORIGINAL => ImageFormat::Original,
            ImageOptions::FORMAT_WEBP => ImageFormat::Webp,
            default => $this->imageProvider->supportsFormat(ImageFormat::Webp) ? ImageFormat::Webp : ImageFormat::Original,
        };

        $img = self::tag('img', ['src' => $url(self::srcWidth($widths, min($width, $maxWidth)), $format), 'srcset' => $srcset($format), 'sizes' => $sizes] + $attributes);
        if (ImageOptions::FORMAT_AUTO !== $options->format || !$this->imageProvider->supportsFormat(ImageFormat::Avif)) {
            return $img;
        }

        // Liip doesn't negotiate formats, so the browser picks AVIF from a source where it supports it
        return '<picture>' . self::tag('source', ['type' => ImageFormat::Avif->mimeType(), 'srcset' => $srcset(ImageFormat::Avif), 'sizes' => $sizes]) . $img . '</picture>';
    }

    private function mediaItem(MediaItemInterface|string|null $media): ?MediaItemInterface
    {
        if (is_string($media)) {
            $media = MediaItemReferences::isUuid($media) ? $this->mediaItemRepository->findOneByUuid(strtolower($media)) : null;
        }

        return null !== $media && MediaKind::Image === $media->getKind() ? $media : null;
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

        return $this->altTextResolver->resolve($mediaItem, $this->renderContext($context)->localeCode) ?? '';
    }

    /**
     * The render context of a CMS template, or the shop's anywhere else
     *
     * @param array<string, mixed> $context
     */
    private function renderContext(array $context): RenderContext
    {
        $renderContext = $context[CMSRuntime::RENDER_CONTEXT] ?? null;

        return $renderContext instanceof RenderContext ? $renderContext : $this->renderContextFactory->create();
    }

    /**
     * The variant the src gives browsers without srcset: the narrowest that covers the rendered width
     *
     * @param non-empty-list<int<1, max>> $widths in ascending order
     *
     * @return int<1, max>
     */
    private static function srcWidth(array $widths, int $width): int
    {
        foreach ($widths as $variantWidth) {
            if ($variantWidth >= $width) {
                return $variantWidth;
            }
        }

        return $widths[array_key_last($widths)];
    }

    /**
     * A grey box at the image's aspect ratio, the one the template gives or 16:9, as wide as the image renders
     */
    private static function placeholder(ImageOptions $options): string
    {
        $style = 'aspect-ratio: ' . self::number($options->aspectRatio ?? self::PLACEHOLDER_ASPECT_RATIO);
        if (null !== $options->width) {
            $style .= match ($options->layout) {
                ImageLayout::Fixed => sprintf('; width: %dpx', $options->width),
                ImageLayout::Constrained => sprintf('; max-width: %dpx', $options->width),
                ImageLayout::FullWidth => '',
            };
        }

        return self::tag('span', ['class' => trim('cms-image-placeholder ' . $options->class), 'style' => $style, 'aria-hidden' => 'true']) . '</span>';
    }

    private static function number(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }

    /**
     * An opening tag with the attributes that have a value, escaped
     *
     * @param array<string, string|int|null> $attributes
     */
    private static function tag(string $name, array $attributes): string
    {
        $html = '<' . $name;
        foreach ($attributes as $attribute => $value) {
            if (null !== $value) {
                $html .= sprintf(' %s="%s"', $attribute, htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
            }
        }

        return $html . '>';
    }
}
