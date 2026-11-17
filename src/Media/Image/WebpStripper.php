<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Cuts the EXIF and XMP chunks out of a WebP without decoding it, and clears their flags in the extended header (VP8X).
 * An animated WebP keeps its orientation unread, since turning it would mean encoding every frame again
 *
 * @internal
 */
final class WebpStripper
{
    private const FORMAT = 'WebP';

    private const FLAG_EXIF = 0x08;

    private const FLAG_XMP = 0x04;

    private const FLAG_ANIMATION = 0x02;

    private function __construct()
    {
    }

    /**
     * @throws UnreadableFileException
     */
    public static function strip(string $webp): StrippedImage
    {
        if ('RIFF' !== Bytes::read($webp, 0, 4, self::FORMAT) || 'WEBP' !== Bytes::read($webp, 8, 4, self::FORMAT)) {
            throw new UnreadableFileException('The WebP doesn\'t start with its RIFF header');
        }

        $end = 8 + Bytes::uint32le($webp, 4, self::FORMAT);
        $chunks = '';
        $orientation = ExifOrientation::UPRIGHT;
        $animated = false;

        for ($offset = 12; $offset < $end;) {
            $fourCc = Bytes::read($webp, $offset, 4, self::FORMAT);
            $length = Bytes::uint32le($webp, $offset + 4, self::FORMAT);
            // A chunk is padded to an even length
            $padded = $length + ($length % 2);
            $data = Bytes::read($webp, $offset + 8, $padded, self::FORMAT);
            $offset += 8 + $padded;

            if ('EXIF' === $fourCc) {
                $orientation = ExifOrientation::read(substr($data, 0, $length));

                continue;
            }

            if ('XMP ' === $fourCc) {
                continue;
            }

            if ('VP8X' === $fourCc && '' !== $data) {
                $animated = 0 !== (ord($data[0]) & self::FLAG_ANIMATION);
                $data[0] = chr(ord($data[0]) & ~(self::FLAG_EXIF | self::FLAG_XMP));
            }

            $chunks .= $fourCc . pack('V', $length) . $data;
        }

        return new StrippedImage('RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks, $animated ? ExifOrientation::UPRIGHT : $orientation);
    }
}
