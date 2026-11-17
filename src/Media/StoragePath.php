<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Where a media item's file sits in the plugin's storage: under its UUID, then a random part that's new for every file
 * it gets, so a replaced file never keeps an old URL that caches may hold, then the original filename as a slug with the
 * extension of its MIME type, so a downloaded document keeps a readable name. Folders exist only in the library, so
 * moving a media item or renaming a folder never moves a file. See "Folders" and "Replacing a file" in docs/spec/media.md
 */
final class StoragePath
{
    private const MAX_SLUG_LENGTH = 80;

    private function __construct()
    {
    }

    /**
     * Such as 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f/3f9a1c2b7d4e5a60/summer-sale.jpg
     */
    public static function for(string $uuid, string $originalFilename, string $extension): string
    {
        return sprintf('%s/%s/%s', $uuid, bin2hex(random_bytes(8)), self::filename($originalFilename, $extension));
    }

    /**
     * The original filename as a slug with the extension, such as summer-sale.jpg for "Summer Sale!.JPEG", which the
     * file is stored under and the permanent URL of a document or video ends with
     */
    public static function filename(string $originalFilename, string $extension): string
    {
        $slug = (new AsciiSlugger())->slug(pathinfo($originalFilename, \PATHINFO_FILENAME))->lower()->truncate(self::MAX_SLUG_LENGTH)->trim('-')->toString();

        return sprintf('%s.%s', '' === $slug ? 'file' : $slug, $extension);
    }
}
