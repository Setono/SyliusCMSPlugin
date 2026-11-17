<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * One variant of an image: its width, the aspect ratio it's cropped to around the focal point, or null for the source's
 * own, and its format. See "Images" in docs/spec/extending.md
 */
final readonly class ImageTransform
{
    /**
     * @param int $width in pixels
     * @param float|null $aspectRatio width divided by height, or null to keep the source's
     * @param FocalPoint $focalPoint the media item's, which a crop keeps in view
     *
     * @throws \InvalidArgumentException when the width or the aspect ratio isn't positive
     */
    public function __construct(
        public int $width,
        public ?float $aspectRatio,
        public ImageFormat $format,
        public FocalPoint $focalPoint = new FocalPoint(),
    ) {
        if ($width < 1) {
            throw new \InvalidArgumentException(sprintf('An image variant\'s width must be a positive number of pixels, not %d', $width));
        }

        if (null !== $aspectRatio && (!($aspectRatio > 0) || is_infinite($aspectRatio))) {
            throw new \InvalidArgumentException(sprintf('An image variant\'s aspect ratio must be a positive number, not %s', $aspectRatio));
        }
    }

    /**
     * The height in pixels the aspect ratio gives, or null when the variant keeps the source's aspect ratio
     *
     * @return int<1, max>|null
     */
    public function height(): ?int
    {
        return null === $this->aspectRatio ? null : max(1, (int) round($this->width / $this->aspectRatio));
    }
}
