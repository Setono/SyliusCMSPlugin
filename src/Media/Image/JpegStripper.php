<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Cuts the metadata segments out of a JPEG without decoding it, so its image data stays byte for byte: EXIF and XMP
 * (APP1), IPTC (APP13), comments, and every other application segment except the three that change how it's decoded,
 * JFIF (APP0), the ICC profile (APP2) and Adobe's colour transform (APP14). Anything after the end of image, such as the
 * extra pictures some cameras append, goes too
 *
 * @internal
 */
final class JpegStripper
{
    private const FORMAT = 'JPEG';

    private const SOI = "\xFF\xD8";

    private const EOI = 0xD9;

    private const SOS = 0xDA;

    private const APP0 = 0xE0;

    private const APP1 = 0xE1;

    private const APP2 = 0xE2;

    private const APP14 = 0xEE;

    private const APP15 = 0xEF;

    private const COM = 0xFE;

    private function __construct()
    {
    }

    /**
     * @throws UnreadableFileException
     */
    public static function strip(string $jpeg): StrippedImage
    {
        if (!str_starts_with($jpeg, self::SOI)) {
            throw new UnreadableFileException('The JPEG doesn\'t start with a start of image marker');
        }

        $stripped = self::SOI;
        $orientation = null;
        $offset = 2;

        while (true) {
            if ("\xFF" !== Bytes::read($jpeg, $offset, 1, self::FORMAT)) {
                throw new UnreadableFileException(sprintf('The JPEG has no marker where one belongs, at byte %d', $offset));
            }

            // A marker may be padded with fill bytes
            while ("\xFF" === Bytes::read($jpeg, $offset, 1, self::FORMAT)) {
                ++$offset;
            }

            $marker = ord(Bytes::read($jpeg, $offset, 1, self::FORMAT));
            ++$offset;

            if (self::EOI === $marker) {
                return new StrippedImage($stripped . "\xFF\xD9", $orientation ?? ExifOrientation::UPRIGHT);
            }

            // Restart and TEM markers stand alone
            if (0x01 === $marker || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $stripped .= "\xFF" . chr($marker);

                continue;
            }

            $length = Bytes::uint16($jpeg, $offset, self::FORMAT);
            if ($length < 2) {
                throw new UnreadableFileException(sprintf('The JPEG has a segment of length %d', $length));
            }

            $data = Bytes::read($jpeg, $offset + 2, $length - 2, self::FORMAT);
            $offset += $length;

            if (self::APP1 === $marker && null === $orientation && str_starts_with($data, "Exif\0\0")) {
                $orientation = ExifOrientation::read($data);
            }

            if (self::keeps($marker, $data)) {
                $stripped .= "\xFF" . chr($marker) . pack('n', $length) . $data;
            }

            if (self::SOS === $marker) {
                $end = self::endOfScan($jpeg, $offset);
                $stripped .= substr($jpeg, $offset, $end - $offset);
                $offset = $end;
            }
        }
    }

    private static function keeps(int $marker, string $data): bool
    {
        return match (true) {
            self::APP0 === $marker => str_starts_with($data, "JFIF\0"),
            self::APP2 === $marker => str_starts_with($data, "ICC_PROFILE\0"),
            self::APP14 === $marker => str_starts_with($data, 'Adobe'),
            $marker >= self::APP0 && $marker <= self::APP15, self::COM === $marker => false,
            default => true,
        };
    }

    /**
     * Where the entropy-coded data after a start of scan ends: at the first marker that's neither a stuffed byte nor a
     * restart marker
     */
    private static function endOfScan(string $jpeg, int $offset): int
    {
        while (true) {
            $ff = strpos($jpeg, "\xFF", $offset);
            if (false === $ff || $ff + 1 >= strlen($jpeg)) {
                throw new UnreadableFileException('The JPEG ends inside its image data');
            }

            $next = ord($jpeg[$ff + 1]);
            if (0x00 === $next || ($next >= 0xD0 && $next <= 0xD7) || 0xFF === $next) {
                $offset = 0xFF === $next ? $ff + 1 : $ff + 2;

                continue;
            }

            return $ff;
        }
    }
}
