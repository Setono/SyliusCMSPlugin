<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;
use Setono\SyliusCMSPlugin\Media\CropRectangle;
use Setono\SyliusCMSPlugin\Media\FocalPoint;

/**
 * The LiipImagine filter that makes an image variant: it crops the image to the variant's aspect ratio around the focal
 * point, then scales it down to the variant's width, never up. Its options arrive as runtime filters from the variant's
 * URL, which LiipImageProvider builds and Liip signs: width, height when the variant has an aspect ratio of its own, and
 * the focal point's x and y. See "Image processing" in docs/spec/media.md
 *
 * @internal
 */
final class TransformFilterLoader implements LoaderInterface
{
    /** The filter's name in Liip, and in a variant's URL */
    public const NAME = 'setono_sylius_cms_transform';

    /**
     * @param array<array-key, mixed> $options
     *
     * @throws \InvalidArgumentException when an option isn't what LiipImageProvider puts in a URL
     */
    public function load(ImageInterface $image, array $options = []): ImageInterface
    {
        $width = self::pixels($options['width'] ?? null, 'width');
        $height = isset($options['height']) ? self::pixels($options['height'], 'height') : null;
        $focalPoint = new FocalPoint(self::coordinate($options['x'] ?? 0.5), self::coordinate($options['y'] ?? 0.5));

        $size = $image->getSize();
        if (null !== $height) {
            $crop = CropRectangle::around(max(1, $size->getWidth()), max(1, $size->getHeight()), $width / $height, $focalPoint);
            if ($crop->width !== $size->getWidth() || $crop->height !== $size->getHeight()) {
                $image = $image->crop(new Point($crop->x, $crop->y), new Box($crop->width, $crop->height));
                $size = $image->getSize();
            }
        }

        if ($width >= $size->getWidth()) {
            return $image;
        }

        return $image->resize(null === $height ? $size->widen($width) : new Box($width, $height));
    }

    /**
     * @return int<1, max>
     */
    private static function pixels(mixed $value, string $name): int
    {
        $pixels = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
        if ($pixels < 1) {
            throw new \InvalidArgumentException(sprintf('An image variant\'s %s must be a positive number of pixels, not %s', $name, get_debug_type($value)));
        }

        return $pixels;
    }

    private static function coordinate(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('A focal point\'s x and y must be numbers, not %s', get_debug_type($value)));
        }

        return (float) $value;
    }
}
