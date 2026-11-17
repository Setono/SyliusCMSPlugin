<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The name a browser sent with a file, as a media item keeps it for its original filename: without a path, and short
 * enough for the column
 *
 * @internal
 */
final class UploadedFilename
{
    private const MAX_LENGTH = 255;

    private function __construct()
    {
    }

    public static function clean(string $filename): string
    {
        $filename = trim(basename(str_replace('\\', '/', $filename)));

        return mb_substr('' === $filename ? 'file' : $filename, -self::MAX_LENGTH);
    }
}
