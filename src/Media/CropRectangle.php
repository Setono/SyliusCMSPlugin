<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The part of an image a variant with another aspect ratio keeps, in pixels from the top left: as much of the image as
 * the aspect ratio allows, centred on the focal point, and moved inside the image when the focal point is near an edge.
 * See "Image processing" in docs/spec/media.md
 */
final readonly class CropRectangle
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {
    }

    /**
     * @param int<1, max> $width the image's width
     * @param int<1, max> $height the image's height
     * @param float $aspectRatio the variant's, width divided by height
     *
     * @throws \InvalidArgumentException when the aspect ratio isn't positive
     */
    public static function around(int $width, int $height, float $aspectRatio, FocalPoint $focalPoint): self
    {
        if (!($aspectRatio > 0) || is_infinite($aspectRatio)) {
            throw new \InvalidArgumentException(sprintf('An aspect ratio must be a positive number, not %s', $aspectRatio));
        }

        if ($width / $height > $aspectRatio) {
            $cropWidth = max(1, min($width, (int) round($height * $aspectRatio)));

            return new self(self::start($focalPoint->x, $width, $cropWidth), 0, $cropWidth, $height);
        }

        $cropHeight = max(1, min($height, (int) round($width / $aspectRatio)));

        return new self(0, self::start($focalPoint->y, $height, $cropHeight), $width, $cropHeight);
    }

    /**
     * Where the crop starts along one side: centred on the focal point, and inside the image
     *
     * @param float $focus the focal point along the side, from 0 to 1
     */
    private static function start(float $focus, int $length, int $cropLength): int
    {
        return max(0, min($length - $cropLength, (int) round($focus * $length - $cropLength / 2)));
    }
}
