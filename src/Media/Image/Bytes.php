<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Reads the parts of a binary file, refusing to read past its end
 *
 * @internal
 */
final class Bytes
{
    private function __construct()
    {
    }

    /**
     * @throws UnreadableFileException when the file ends before $length bytes from $offset
     */
    public static function read(string $data, int $offset, int $length, string $format): string
    {
        if ($offset < 0 || $length < 0 || $offset + $length > strlen($data)) {
            throw new UnreadableFileException(sprintf('The %s ends before its structure does', $format));
        }

        return substr($data, $offset, $length);
    }

    /**
     * A big-endian unsigned 16-bit integer
     */
    public static function uint16(string $data, int $offset, string $format): int
    {
        /** @var array{1: int} $value */
        $value = unpack('n', self::read($data, $offset, 2, $format));

        return $value[1];
    }

    /**
     * A big-endian unsigned 32-bit integer
     */
    public static function uint32(string $data, int $offset, string $format): int
    {
        /** @var array{1: int} $value */
        $value = unpack('N', self::read($data, $offset, 4, $format));

        return $value[1];
    }

    /**
     * A little-endian unsigned 32-bit integer
     */
    public static function uint32le(string $data, int $offset, string $format): int
    {
        /** @var array{1: int} $value */
        $value = unpack('V', self::read($data, $offset, 4, $format));

        return $value[1];
    }
}
