<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Which media items have image variants: images whose dimensions are known, except SVG and GIF, which are served as
 * uploaded at every width. An SVG is sharp at any size, and a GIF may be animated, which a variant would freeze. See
 * "Image processing" in docs/spec/media.md
 */
final class ImageVariants
{
    /** The MIME types of the images served as uploaded */
    private const AS_UPLOADED = ['image/svg+xml', 'image/gif'];

    private function __construct()
    {
    }

    public static function areMadeFor(MediaItemInterface $mediaItem): bool
    {
        return MediaKind::Image === $mediaItem->getKind() &&
            !in_array($mediaItem->getMimeType(), self::AS_UPLOADED, true) &&
            null !== $mediaItem->getWidth() &&
            null !== $mediaItem->getHeight()
        ;
    }
}
