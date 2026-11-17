<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

/**
 * An image with its embedded metadata cut out, and the EXIF orientation it had
 *
 * @internal
 */
final readonly class StrippedImage
{
    /**
     * @param int<1, 8> $orientation
     */
    public function __construct(
        public string $content,
        public int $orientation = ExifOrientation::UPRIGHT,
    ) {
    }
}
