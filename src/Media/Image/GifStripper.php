<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Cuts the comments and the application extensions other than the animation loop (NETSCAPE2.0 and ANIMEXTS1.0) out of a
 * GIF without decoding it, so an animated GIF keeps every frame. XMP is an application extension. Anything after the
 * trailer goes too
 *
 * @internal
 */
final class GifStripper
{
    private const FORMAT = 'GIF';

    private const IMAGE = "\x2C";

    private const EXTENSION = "\x21";

    private const TRAILER = "\x3B";

    private const COMMENT = 0xFE;

    private const APPLICATION = 0xFF;

    private const LOOPS = ['NETSCAPE2.0', 'ANIMEXTS1.0'];

    private function __construct()
    {
    }

    /**
     * @throws UnreadableFileException
     */
    public static function strip(string $gif): StrippedImage
    {
        $header = Bytes::read($gif, 0, 6, self::FORMAT);
        if ('GIF87a' !== $header && 'GIF89a' !== $header) {
            throw new UnreadableFileException('The GIF doesn\'t start with its header');
        }

        // The header and the logical screen descriptor, with the global colour table when there is one
        $offset = 13 + self::colourTableLength(ord(Bytes::read($gif, 10, 1, self::FORMAT)));
        $stripped = Bytes::read($gif, 0, $offset, self::FORMAT);

        while (true) {
            $introducer = Bytes::read($gif, $offset, 1, self::FORMAT);

            if (self::TRAILER === $introducer) {
                return new StrippedImage($stripped . self::TRAILER);
            }

            if (self::IMAGE === $introducer) {
                // The image descriptor, its local colour table, the LZW minimum code size, then the image data
                $start = $offset + 10 + self::colourTableLength(ord(Bytes::read($gif, $offset + 9, 1, self::FORMAT))) + 1;
                $end = self::endOfSubBlocks($gif, $start);
                $stripped .= substr($gif, $offset, $end - $offset);
                $offset = $end;

                continue;
            }

            if (self::EXTENSION !== $introducer) {
                throw new UnreadableFileException(sprintf('The GIF has no block where one belongs, at byte %d', $offset));
            }

            $label = ord(Bytes::read($gif, $offset + 1, 1, self::FORMAT));
            $end = self::endOfSubBlocks($gif, $offset + 2);
            if (self::keeps($label, substr($gif, $offset + 3, 11))) {
                $stripped .= substr($gif, $offset, $end - $offset);
            }

            $offset = $end;
        }
    }

    /**
     * @param string $identifier an application extension's identifier and authentication code
     */
    private static function keeps(int $label, string $identifier): bool
    {
        return match ($label) {
            self::COMMENT => false,
            self::APPLICATION => in_array($identifier, self::LOOPS, true),
            default => true,
        };
    }

    /**
     * The length of the colour table a descriptor's packed fields announce
     */
    private static function colourTableLength(int $packed): int
    {
        return 0 === ($packed & 0x80) ? 0 : 3 * (1 << (($packed & 0x07) + 1));
    }

    /**
     * Where the data sub-blocks starting at $offset end, after their terminator
     */
    private static function endOfSubBlocks(string $gif, int $offset): int
    {
        while (true) {
            $size = ord(Bytes::read($gif, $offset, 1, self::FORMAT));
            ++$offset;
            if (0 === $size) {
                return $offset;
            }

            Bytes::read($gif, $offset, $size, self::FORMAT);
            $offset += $size;
        }
    }
}
