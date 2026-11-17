<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

/**
 * The EXIF orientation of an image: how it's turned or mirrored when shown, from 1 (upright) to 8. Read from the first
 * image file directory of EXIF's TIFF structure, in either byte order. Anything unreadable counts as upright, since the
 * metadata is stripped anyway
 *
 * @internal
 */
final class ExifOrientation
{
    public const UPRIGHT = 1;

    private const TAG = 0x0112;

    private const SHORT = 3;

    private function __construct()
    {
    }

    /**
     * @param string $exif EXIF's TIFF structure, optionally after the "Exif\0\0" header a JPEG's APP1 segment has
     *
     * @return int<1, 8>
     */
    public static function read(string $exif): int
    {
        if (str_starts_with($exif, "Exif\0\0")) {
            $exif = substr($exif, 6);
        }

        $order = substr($exif, 0, 2);
        if ('MM' !== $order && 'II' !== $order) {
            return self::UPRIGHT;
        }

        $short = 'MM' === $order ? 'n' : 'v';
        $long = 'MM' === $order ? 'N' : 'V';

        $ifd = self::unpack($long, $exif, 4, 4);
        $entries = self::unpack($short, $exif, $ifd, 2);
        for ($i = 0; $i < $entries; ++$i) {
            $entry = $ifd + 2 + $i * 12;
            if (self::TAG === self::unpack($short, $exif, $entry, 2) && self::SHORT === self::unpack($short, $exif, $entry + 2, 2)) {
                $orientation = self::unpack($short, $exif, $entry + 8, 2);

                return $orientation >= 1 && $orientation <= 8 ? $orientation : self::UPRIGHT;
            }
        }

        return self::UPRIGHT;
    }

    /**
     * The integer at $offset, or 0 past the end
     */
    private static function unpack(string $format, string $data, int $offset, int $length): int
    {
        if ($offset < 0 || $offset + $length > strlen($data)) {
            return 0;
        }

        /** @var array{1: int} $value */
        $value = unpack($format, substr($data, $offset, $length));

        return $value[1];
    }
}
