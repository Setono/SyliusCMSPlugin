<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Empties the Exif and XMP items of an AVIF without decoding it. An AVIF is an ISOBMFF file whose metadata are items of
 * its meta box, found through the item information (iinf) and stored where the item locations (iloc) point, usually in
 * the mdat box. Cutting them out would move the image data those offsets point at too, so they're overwritten in place
 * at their length: the Exif item with an Exif payload without tags, since libavif (and so GD) refuses an image whose
 * Exif item isn't one, and the XMP item with an empty XMP packet padded with spaces. Every other byte is kept.
 * An AVIF's orientation is its irot and imir properties, not its Exif orientation, so it's always upright here
 *
 * @internal
 */
final class AvifStripper
{
    private const FORMAT = 'AVIF';

    /** An Exif payload: no bytes before the TIFF header, a big-endian TIFF header, and a first IFD without entries */
    private const EMPTY_EXIF = "\0\0\0\0" . "MM\0*" . "\0\0\0\x08" . "\0\0" . "\0\0\0\0";

    private const EMPTY_XMP = '<x:xmpmeta xmlns:x="adobe:ns:meta/"/>';

    private const EXIF = 'Exif';

    private const XMP = 'XMP';

    private function __construct()
    {
    }

    /**
     * @throws UnreadableFileException
     */
    public static function strip(string $avif): StrippedImage
    {
        $boxes = self::boxes($avif, 0, strlen($avif));
        if ('ftyp' !== ($boxes[0]->type ?? null)) {
            throw new UnreadableFileException('The AVIF doesn\'t start with its ftyp box');
        }

        foreach ($boxes as $box) {
            if ('meta' === $box->type) {
                $avif = self::emptyMetadataItems($avif, $box);
            }
        }

        return new StrippedImage($avif);
    }

    private static function emptyMetadataItems(string $avif, AvifBox $meta): string
    {
        // The meta box is a full box: a version and flags before its children
        $children = self::boxes($avif, $meta->contentOffset + 4, $meta->end);

        $kinds = [];
        $locations = [];
        $idat = null;
        foreach ($children as $child) {
            match ($child->type) {
                'iinf' => $kinds = self::metadataItems($avif, $child),
                'iloc' => $locations = self::itemLocations($avif, $child),
                'idat' => $idat = $child,
                default => null,
            };
        }

        foreach ($kinds as $itemId => $kind) {
            $location = $locations[$itemId] ?? null;
            if (null === $location) {
                continue;
            }

            $extents = self::fileExtents($avif, $location, $idat);
            if (null === $extents) {
                continue;
            }

            $length = array_sum(array_column($extents, 1));
            $empty = self::EXIF === $kind
                ? str_pad(substr(self::EMPTY_EXIF, 0, $length), $length, "\0")
                : str_pad(strlen(self::EMPTY_XMP) <= $length ? self::EMPTY_XMP : '', $length);

            foreach ($extents as [$offset, $extentLength]) {
                $avif = substr_replace($avif, substr($empty, 0, $extentLength), $offset, $extentLength);
                $empty = substr($empty, $extentLength);
            }
        }

        return $avif;
    }

    /**
     * The Exif and XMP items the item information lists
     *
     * @return array<int, self::EXIF|self::XMP> by item ID
     */
    private static function metadataItems(string $avif, AvifBox $iinf): array
    {
        // A full box, then the entry count: 16 bits in version 0, 32 bits otherwise
        $version = ord(Bytes::read($avif, $iinf->contentOffset, 1, self::FORMAT));
        $items = [];
        foreach (self::boxes($avif, $iinf->contentOffset + 4 + (0 === $version ? 2 : 4), $iinf->end) as $infe) {
            if ('infe' !== $infe->type) {
                continue;
            }

            // Versions 0 and 1 have no item type, and AVIF requires version 2 or 3
            $version = ord(Bytes::read($avif, $infe->contentOffset, 1, self::FORMAT));
            if ($version < 2) {
                continue;
            }

            $offset = $infe->contentOffset + 4;
            $itemId = 2 === $version ? Bytes::uint16($avif, $offset, self::FORMAT) : Bytes::uint32($avif, $offset, self::FORMAT);
            // After the item ID, the item protection index (16 bits)
            $offset += (2 === $version ? 2 : 4) + 2;
            $itemType = Bytes::read($avif, $offset, 4, self::FORMAT);
            [, $offset] = self::string($avif, $offset + 4, $infe->end);

            if ('Exif' === $itemType) {
                $items[$itemId] = self::EXIF;
            } elseif ('mime' === $itemType && 'application/rdf+xml' === self::string($avif, $offset, $infe->end)[0]) {
                $items[$itemId] = self::XMP;
            }
        }

        return $items;
    }

    /**
     * @return array<int, AvifItemLocation> by item ID
     */
    private static function itemLocations(string $avif, AvifBox $iloc): array
    {
        $version = ord(Bytes::read($avif, $iloc->contentOffset, 1, self::FORMAT));
        $offset = $iloc->contentOffset + 4;
        $sizes = ord(Bytes::read($avif, $offset, 1, self::FORMAT));
        $offsetSize = $sizes >> 4;
        $lengthSize = $sizes & 0x0F;
        $sizes = ord(Bytes::read($avif, $offset + 1, 1, self::FORMAT));
        $baseOffsetSize = $sizes >> 4;
        // Only versions 1 and 2 have an extent index
        $indexSize = 0 === $version ? 0 : $sizes & 0x0F;
        $offset += 2;

        $itemCount = $version < 2 ? Bytes::uint16($avif, $offset, self::FORMAT) : Bytes::uint32($avif, $offset, self::FORMAT);
        $offset += $version < 2 ? 2 : 4;

        $locations = [];
        for ($i = 0; $i < $itemCount; ++$i) {
            $itemId = $version < 2 ? Bytes::uint16($avif, $offset, self::FORMAT) : Bytes::uint32($avif, $offset, self::FORMAT);
            $offset += $version < 2 ? 2 : 4;

            $constructionMethod = 0;
            if (0 !== $version) {
                $constructionMethod = Bytes::uint16($avif, $offset, self::FORMAT) & 0x0F;
                $offset += 2;
            }

            $dataReferenceIndex = Bytes::uint16($avif, $offset, self::FORMAT);
            $baseOffset = self::uint($avif, $offset + 2, $baseOffsetSize);
            $offset += 2 + $baseOffsetSize;

            $extentCount = Bytes::uint16($avif, $offset, self::FORMAT);
            $offset += 2;

            $extents = [];
            for ($j = 0; $j < $extentCount; ++$j) {
                $offset += $indexSize;
                $extents[] = [$baseOffset + self::uint($avif, $offset, $offsetSize), self::uint($avif, $offset + $offsetSize, $lengthSize)];
                $offset += $offsetSize + $lengthSize;
            }

            $locations[$itemId] = new AvifItemLocation($constructionMethod, $dataReferenceIndex, $extents);
        }

        return $locations;
    }

    /**
     * Where an item's extents are in the file, or null when they aren't in it: in another file, or in another item
     *
     * @return list<array{int, int}>|null offsets and lengths
     */
    private static function fileExtents(string $avif, AvifItemLocation $location, ?AvifBox $idat): ?array
    {
        if (0 !== $location->dataReferenceIndex) {
            return null;
        }

        // Construction method 0 points into the file, 1 into the idat box
        [$start, $end] = match ($location->constructionMethod) {
            0 => [0, strlen($avif)],
            1 => null === $idat ? [null, null] : [$idat->contentOffset, $idat->end],
            default => [null, null],
        };
        if (null === $start || null === $end) {
            return null;
        }

        $extents = [];
        foreach ($location->extents as [$offset, $length]) {
            $offset += $start;
            // A length of 0 means the rest of the data
            $length = 0 === $length ? $end - $offset : $length;
            if ($offset + $length > $end) {
                throw new UnreadableFileException('An item of the AVIF ends after its data does');
            }

            $extents[] = [$offset, $length];
        }

        return $extents;
    }

    /**
     * The boxes between two offsets, each a 32-bit size, a type and its content. A size of 1 means a 64-bit size follows
     * the type, and a size of 0 means the box runs to the end
     *
     * @return list<AvifBox>
     */
    private static function boxes(string $avif, int $offset, int $end): array
    {
        $boxes = [];
        while ($offset < $end) {
            $size = Bytes::uint32($avif, $offset, self::FORMAT);
            $type = Bytes::read($avif, $offset + 4, 4, self::FORMAT);
            $contentOffset = $offset + 8;
            if (1 === $size) {
                $size = self::uint($avif, $contentOffset, 8);
                $contentOffset += 8;
            } elseif (0 === $size) {
                $size = $end - $offset;
            }

            if ($size < $contentOffset - $offset || $offset + $size > $end) {
                throw new UnreadableFileException(sprintf('The AVIF\'s %s box doesn\'t fit in it', $type));
            }

            $boxes[] = new AvifBox($type, $contentOffset, $offset + $size);
            $offset += $size;
        }

        return $boxes;
    }

    /**
     * A big-endian unsigned integer of 0, 4 or 8 bytes
     */
    private static function uint(string $avif, int $offset, int $size): int
    {
        return match ($size) {
            0 => 0,
            4 => Bytes::uint32($avif, $offset, self::FORMAT),
            8 => Bytes::uint64($avif, $offset, self::FORMAT),
            default => throw new UnreadableFileException(sprintf('The AVIF has an integer of %d bytes', $size)),
        };
    }

    /**
     * A null-terminated string, and the offset after it
     *
     * @return array{string, int}
     */
    private static function string(string $avif, int $offset, int $end): array
    {
        $null = strpos($avif, "\0", $offset);
        if (false === $null || $null >= $end) {
            throw new UnreadableFileException('A string of the AVIF doesn\'t end in its box');
        }

        return [substr($avif, $offset, $null - $offset), $null + 1];
    }
}
