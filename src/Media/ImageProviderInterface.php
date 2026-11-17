<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Where cms_image() gets the URL of each variant of an image. The default builds LiipImagine's resolve URLs, which
 * generate a variant on its first request. A project swaps in an image CDN by aliasing this interface to a service of
 * its own. See "Image processing" in docs/spec/media.md and "Images" in docs/spec/extending.md
 */
interface ImageProviderInterface
{
    /**
     * Builds the URL without calling the storage
     */
    public function url(MediaItemInterface $mediaItem, ImageTransform $transform): string;

    /**
     * Whether it can give variants in the format, which cms_image() offers only then, such as AVIF where the server's
     * image library supports it
     */
    public function supportsFormat(ImageFormat $format): bool;
}
