<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * One image as cms_image() renders it at the options' layout and width, cropped to an aspect ratio: its rendered size,
 * the widths it's offered at and their URLs in a format, through the image provider. An image without variants, such as
 * an SVG, has one URL at every width. See "The image helper" in docs/spec/media.md
 *
 * @internal
 */
final readonly class ImageRendition
{
    /**
     * @param int|null $width the rendered width, or null when neither the options nor the image give one
     * @param int|null $height the rendered height, or null when the aspect ratio isn't known
     * @param list<int<1, max>> $widths the variants' widths in ascending order, or none for an image without variants
     * @param float|null $aspectRatio the one the variants are cropped to, or null for the source's
     * @param string|null $sizes the sizes attribute, or null for a fixed image or one without variants
     */
    private function __construct(
        private ImageProviderInterface $imageProvider,
        private MediaItemInterface $mediaItem,
        private ImageLayout $layout,
        public ?int $width,
        public ?int $height,
        private array $widths,
        private ?float $aspectRatio,
        public ?string $sizes,
    ) {
    }

    /**
     * @param MediaItemInterface $mediaItem an image
     * @param float|null $aspectRatio the one the template gives, or null for the image's own
     * @param list<int<1, max>> $ladder media.widths, in ascending order
     */
    public static function of(ImageProviderInterface $imageProvider, MediaItemInterface $mediaItem, ImageOptions $options, ?float $aspectRatio, array $ladder): self
    {
        $sourceWidth = $mediaItem->getWidth();
        $sourceHeight = $mediaItem->getHeight();
        $renderedAspectRatio = $aspectRatio ?? (null !== $sourceWidth && null !== $sourceHeight && $sourceHeight > 0 ? $sourceWidth / $sourceHeight : null);
        $width = $options->width ?? $sourceWidth;
        $height = null === $width || null === $renderedAspectRatio ? null : max(1, (int) round($width / $renderedAspectRatio));

        if (!ImageVariants::areMadeFor($mediaItem) || null === $width || null === $sourceWidth || null === $sourceHeight) {
            return new self($imageProvider, $mediaItem, $options->layout, $width, $height, [], null, null);
        }

        // The widest a variant can be without upscaling: the source's width, or its crop's at another aspect ratio
        $maxWidth = null === $aspectRatio ? $sourceWidth : max(1, min($sourceWidth, (int) floor($sourceHeight * $aspectRatio)));
        $sizes = ImageLayout::Fixed === $options->layout ? null : ($options->sizes ?? $options->layout->sizes($width));

        return new self(
            $imageProvider,
            $mediaItem,
            $options->layout,
            $width,
            $height,
            $options->layout->widths(max(1, $width), max(1, $maxWidth), $ladder),
            $aspectRatio,
            $sizes,
        );
    }

    public function hasVariants(): bool
    {
        return [] !== $this->widths;
    }

    /**
     * The URL browsers without srcset get: the narrowest variant that covers the rendered width, or the image as uploaded
     */
    public function src(ImageFormat $format): string
    {
        // The narrowest that covers the width, or else the widest
        $srcWidth = null;
        foreach ($this->widths as $variantWidth) {
            $srcWidth = $variantWidth;
            if ($variantWidth >= $this->width) {
                break;
            }
        }

        if (null === $srcWidth) {
            // Served as uploaded at every width, so the transform asks for nothing in particular
            return $this->imageProvider->url($this->mediaItem, new ImageTransform(max(1, $this->width ?? 1), null, ImageFormat::Original, $this->mediaItem->getFocalPoint()));
        }

        return $this->url($srcWidth, $format);
    }

    /**
     * Each variant's URL with its width, or its density for a fixed image. An image without variants has its one URL
     */
    public function srcset(ImageFormat $format): string
    {
        if (!$this->hasVariants()) {
            return $this->src($format);
        }

        $candidates = [];
        foreach ($this->widths as $i => $variantWidth) {
            $candidates[] = $this->url($variantWidth, $format) . ' ' . (ImageLayout::Fixed === $this->layout ? ($i + 1) . 'x' : $variantWidth . 'w');
        }

        return implode(', ', $candidates);
    }

    /**
     * @param int<1, max> $width
     */
    private function url(int $width, ImageFormat $format): string
    {
        return $this->imageProvider->url($this->mediaItem, new ImageTransform($width, $this->aspectRatio, $format, $this->mediaItem->getFocalPoint()));
    }
}
