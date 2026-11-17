<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * Sizes of files: read as php.ini writes them, such as 20M, and written for editors, such as 20 MB. A kilobyte is 1024
 * bytes, as PHP counts them
 */
final class FileSize
{
    private const UNITS = ['B', 'KB', 'MB', 'GB'];

    private function __construct()
    {
    }

    /**
     * @return positive-int
     *
     * @throws \InvalidArgumentException when $size isn't a positive number of bytes, optionally followed by K, M or G
     */
    public static function parse(int|string $size): int
    {
        if (is_int($size)) {
            $size = (string) $size;
        }

        if (1 !== preg_match('/^([1-9][0-9]*)([kmg]?)$/i', $size, $matches)) {
            throw new \InvalidArgumentException(sprintf('The size "%s" must be a positive number of bytes, optionally followed by K, M or G, such as 20M', $size));
        }

        /** @var positive-int $bytes */
        $bytes = (int) $matches[1] * 1024 ** (int) array_search(strtoupper($matches[2]), ['', 'K', 'M', 'G'], true);

        return $bytes;
    }

    /**
     * In the largest unit it's at least one of, rounded to one decimal, such as 2.3 MB or 20 MB
     */
    public static function format(int $bytes): string
    {
        $unit = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $unit < count(self::UNITS) - 1) {
            $size /= 1024;
            ++$unit;
        }

        return sprintf('%s %s', rtrim(rtrim(number_format($size, 1, '.', ''), '0'), '.'), self::UNITS[$unit]);
    }
}
