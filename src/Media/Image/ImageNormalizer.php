<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Imagine\Exception\Exception as ImagineException;
use Imagine\Image\ImagineInterface;
use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * What an uploaded image goes through before it's stored: its embedded metadata is stripped, so location and device data
 * never leak, and its EXIF orientation is applied. An upright image keeps its image data byte for byte, and only a turned
 * or mirrored one is encoded again, with the image library LiipImagine uses. Files of other types are left as they are.
 * Doing it twice gives the same file, so a stored original uploaded again is found as a duplicate.
 * See "EXIF" in docs/spec/media.md
 */
final readonly class ImageNormalizer
{
    /** The format Imagine encodes an image of each MIME type it turns in */
    private const FORMATS = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const FLIP = 'flip';

    /**
     * How to turn an image of each EXIF orientation upright: mirror it, then rotate it clockwise
     *
     * @var array<int, list<self::FLIP|int>>
     */
    private const TRANSFORMATIONS = [
        2 => [self::FLIP],
        3 => [180],
        4 => [self::FLIP, 180],
        5 => [self::FLIP, -90],
        6 => [90],
        7 => [self::FLIP, 90],
        8 => [-90],
    ];

    public function __construct(private ImagineInterface $imagine)
    {
    }

    /**
     * @throws UnreadableFileException when the image isn't what its MIME type says, or is cut short
     */
    public function normalize(string $content, string $mimeType): string
    {
        $stripped = self::strip($content, $mimeType);
        if (null === $stripped) {
            return $content;
        }

        $format = self::FORMATS[$mimeType] ?? null;
        if (ExifOrientation::UPRIGHT === $stripped->orientation || null === $format) {
            return $stripped->content;
        }

        try {
            $image = $this->imagine->load($content);
            foreach (self::TRANSFORMATIONS[$stripped->orientation] as $transformation) {
                self::FLIP === $transformation ? $image->flipHorizontally() : $image->rotate($transformation);
            }

            $encoded = $image->strip()->get($format, ['jpeg_quality' => 92, 'webp_quality' => 92]);
        } catch (ImagineException $e) {
            throw new UnreadableFileException(sprintf('The image couldn\'t be turned upright: %s', $e->getMessage()), previous: $e);
        }

        // Image libraries write metadata of their own, such as GD's comment in a JPEG
        return self::strip($encoded, $mimeType)?->content ?? $encoded;
    }

    private static function strip(string $content, string $mimeType): ?StrippedImage
    {
        return match ($mimeType) {
            'image/jpeg' => JpegStripper::strip($content),
            'image/png' => PngStripper::strip($content),
            'image/webp' => WebpStripper::strip($content),
            'image/gif' => GifStripper::strip($content),
            default => null,
        };
    }
}
