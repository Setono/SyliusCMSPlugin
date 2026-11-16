<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Video;

/**
 * Reads a video's dimensions from the file itself, without ffprobe: an MP4's from its first video track's header, turned
 * when the track is shown turned, as phones record portrait videos, and a WebM's from its first video track. A video it
 * can't read has no dimensions, which doesn't stop it being uploaded. See "Metadata" in docs/spec/media.md
 *
 * @internal
 */
final class VideoDimensions
{
    /** The most of a movie box or a WebM's start it reads, since both hold only metadata */
    private const MAX_READ = 16 * 1024 * 1024;

    private const EBML_SEGMENT = 0x18538067;

    private const EBML_TRACKS = 0x1654AE6B;

    private const EBML_TRACK_ENTRY = 0xAE;

    private const EBML_TRACK_TYPE = 0x83;

    private const EBML_VIDEO = 0xE0;

    private const EBML_PIXEL_WIDTH = 0xB0;

    private const EBML_PIXEL_HEIGHT = 0xBA;

    private const EBML_CLUSTER = 0x1F43B675;

    private function __construct()
    {
    }

    /**
     * @return array{int, int}|null the width and height in pixels
     */
    public static function read(string $path, string $mimeType): ?array
    {
        $file = @fopen($path, 'rb');
        if (false === $file) {
            return null;
        }

        try {
            return match ($mimeType) {
                'video/mp4' => self::mp4($file),
                'video/webm' => self::webm((string) fread($file, self::MAX_READ)),
                default => null,
            };
        } finally {
            fclose($file);
        }
    }

    /**
     * @param resource $file
     *
     * @return array{int, int}|null
     */
    private static function mp4($file): ?array
    {
        $size = fstat($file)['size'] ?? 0;
        // The movie box may come before or after the media data, so the top-level boxes are walked without reading them
        for ($offset = 0; $offset + 8 <= $size;) {
            fseek($file, $offset);
            /** @var array{size: int, type: string}|false $header */
            $header = unpack('Nsize/a4type', (string) fread($file, 8));
            if (false === $header) {
                return null;
            }

            $boxSize = $header['size'];
            $headerSize = 8;
            if (1 === $boxSize) {
                /** @var array{1: int}|false $large */
                $large = unpack('J', (string) fread($file, 8));
                $boxSize = false === $large ? 0 : $large[1];
                $headerSize = 16;
            } elseif (0 === $boxSize) {
                $boxSize = $size - $offset;
            }

            if ($boxSize < $headerSize || $offset + $boxSize > $size) {
                return null;
            }

            if ('moov' === $header['type']) {
                $length = $boxSize - $headerSize;

                return $length < 1 || $length > self::MAX_READ ? null : self::tracks((string) fread($file, $length));
            }

            $offset += $boxSize;
        }

        return null;
    }

    /**
     * The dimensions in the first track header that has any, since an audio track's are zero
     *
     * @return array{int, int}|null
     */
    private static function tracks(string $movie): ?array
    {
        foreach (self::boxes($movie) as $type => $track) {
            if ('trak' !== $type) {
                continue;
            }

            foreach (self::boxes($track) as $childType => $header) {
                if ('tkhd' === $childType && null !== $dimensions = self::trackHeader($header)) {
                    return $dimensions;
                }
            }
        }

        return null;
    }

    /**
     * @return \Generator<string, string> each child box's data by its type
     */
    private static function boxes(string $data): \Generator
    {
        for ($offset = 0; $offset + 8 <= strlen($data);) {
            /** @var array{size: int, type: string} $header */
            $header = unpack('Nsize/a4type', $data, $offset);
            if ($header['size'] < 8 || $offset + $header['size'] > strlen($data)) {
                return;
            }

            yield $header['type'] => substr($data, $offset + 8, $header['size'] - 8);
            $offset += $header['size'];
        }
    }

    /**
     * @return array{int, int}|null
     */
    private static function trackHeader(string $header): ?array
    {
        // Version 1 has 64-bit times, so its matrix and dimensions come 12 bytes later
        $matrix = 0 === ord($header[0] ?? "\0") ? 40 : 52;
        if (strlen($header) < $matrix + 44) {
            return null;
        }

        /** @var array{a: int, b: int, c: int, d: int} $transform */
        $transform = unpack('Na/Nb/x4/Nc/Nd', $header, $matrix);
        /** @var array{width: int, height: int} $dimensions */
        $dimensions = unpack('Nwidth/Nheight', $header, $matrix + 36);
        // 16.16 fixed point
        $width = $dimensions['width'] >> 16;
        $height = $dimensions['height'] >> 16;
        if (0 === $width || 0 === $height) {
            return null;
        }

        // A matrix without scaling on its diagonal turns the picture a quarter
        return 0 === $transform['a'] && 0 === $transform['d'] && 0 !== $transform['b'] && 0 !== $transform['c'] ? [$height, $width] : [$width, $height];
    }

    /**
     * @return array{int, int}|null
     */
    private static function webm(string $start): ?array
    {
        foreach (self::elements($start, 0, strlen($start)) as [$id, $offset, $end]) {
            if (self::EBML_SEGMENT !== $id) {
                continue;
            }

            foreach (self::elements($start, $offset, $end) as [$childId, $childOffset, $childEnd]) {
                if (self::EBML_CLUSTER === $childId) {
                    return null;
                }

                if (self::EBML_TRACKS === $childId) {
                    return self::webmTracks($start, $childOffset, $childEnd);
                }
            }
        }

        return null;
    }

    /**
     * @return array{int, int}|null
     */
    private static function webmTracks(string $data, int $offset, int $end): ?array
    {
        foreach (self::elements($data, $offset, $end) as [$id, $entryOffset, $entryEnd]) {
            if (self::EBML_TRACK_ENTRY !== $id) {
                continue;
            }

            $type = null;
            $dimensions = [self::EBML_PIXEL_WIDTH => 0, self::EBML_PIXEL_HEIGHT => 0];
            foreach (self::elements($data, $entryOffset, $entryEnd) as [$childId, $childOffset, $childEnd]) {
                if (self::EBML_TRACK_TYPE === $childId) {
                    $type = self::unsigned(substr($data, $childOffset, $childEnd - $childOffset));
                }

                if (self::EBML_VIDEO === $childId) {
                    foreach (self::elements($data, $childOffset, $childEnd) as [$videoId, $videoOffset, $videoEnd]) {
                        if (array_key_exists($videoId, $dimensions)) {
                            $dimensions[$videoId] = self::unsigned(substr($data, $videoOffset, $videoEnd - $videoOffset));
                        }
                    }
                }
            }

            // Track type 1 is video
            if (1 === $type && $dimensions[self::EBML_PIXEL_WIDTH] > 0 && $dimensions[self::EBML_PIXEL_HEIGHT] > 0) {
                return [$dimensions[self::EBML_PIXEL_WIDTH], $dimensions[self::EBML_PIXEL_HEIGHT]];
            }
        }

        return null;
    }

    /**
     * The EBML elements between $offset and $end. An element of unknown size, such as a live recording's segment, runs
     * to $end
     *
     * @return \Generator<int, array{int, int, int}> each element's ID, and where its data starts and ends
     */
    private static function elements(string $data, int $offset, int $end): \Generator
    {
        $end = min($end, strlen($data));
        while ($offset < $end) {
            $id = self::vint($data, $offset, true);
            if (null === $id || null === $id[0]) {
                return;
            }

            $offset += $id[1];
            $size = self::vint($data, $offset, false);
            if (null === $size) {
                return;
            }

            $offset += $size[1];
            $elementEnd = null === $size[0] ? $end : min($offset + $size[0], $end);

            yield [$id[0], $offset, $elementEnd];
            $offset = $elementEnd;
        }
    }

    /**
     * An EBML variable-length integer: an element ID keeps its length marker, and a size drops it. A size of all ones is
     * unknown, which is null
     *
     * @return array{int|null, int}|null the value and its length in bytes
     */
    private static function vint(string $data, int $offset, bool $keepMarker): ?array
    {
        if ($offset >= strlen($data)) {
            return null;
        }

        $first = ord($data[$offset]);
        $length = 1;
        while ($length <= 8 && 0 === ($first & (0x80 >> ($length - 1)))) {
            ++$length;
        }

        if ($length > 8 || $offset + $length > strlen($data)) {
            return null;
        }

        $value = $keepMarker ? $first : $first & ((0x80 >> ($length - 1)) - 1);
        $allOnes = $value === (0x80 >> ($length - 1)) - 1;
        for ($i = 1; $i < $length; ++$i) {
            $byte = ord($data[$offset + $i]);
            $allOnes = $allOnes && 0xFF === $byte;
            $value = ($value << 8) | $byte;
        }

        return [!$keepMarker && $allOnes ? null : $value, $length];
    }

    private static function unsigned(string $bytes): int
    {
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
        }

        return $value;
    }
}
