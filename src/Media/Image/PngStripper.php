<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Cuts the metadata chunks out of a PNG without decoding it: text (tEXt, zTXt and iTXt, which carry XMP too), EXIF
 * (eXIf) and the modification time (tIME). Anything after the image end goes too
 *
 * @internal
 */
final class PngStripper
{
    private const FORMAT = 'PNG';

    private const SIGNATURE = "\x89PNG\r\n\x1A\n";

    private const METADATA = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];

    private function __construct()
    {
    }

    /**
     * @throws UnreadableFileException
     */
    public static function strip(string $png): StrippedImage
    {
        if (!str_starts_with($png, self::SIGNATURE)) {
            throw new UnreadableFileException('The PNG doesn\'t start with its signature');
        }

        $stripped = self::SIGNATURE;
        $orientation = ExifOrientation::UPRIGHT;
        $offset = strlen(self::SIGNATURE);

        while (true) {
            $length = Bytes::uint32($png, $offset, self::FORMAT);
            $type = Bytes::read($png, $offset + 4, 4, self::FORMAT);
            // Length, type, data and CRC
            $chunk = Bytes::read($png, $offset, 12 + $length, self::FORMAT);
            $offset += 12 + $length;

            if ('eXIf' === $type) {
                $orientation = ExifOrientation::read(substr($chunk, 8, $length));
            }

            if (!in_array($type, self::METADATA, true)) {
                $stripped .= $chunk;
            }

            if ('IEND' === $type) {
                return new StrippedImage($stripped, $orientation);
            }
        }
    }
}
