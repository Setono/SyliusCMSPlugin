<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * What the media library shows: a folder (All media unless one is given, or Unsorted), the search and the filters.
 * The unused filter joins with the usage index (#249). See "Search and filters" in docs/spec/media.md
 */
final readonly class MediaFilter
{
    /**
     * @param string|null $search matches the name, the original filename and the alt text in every locale
     * @param list<string>|null $missingAltIn the store's locales, to show only images without alt text in one or more of
     *                                        them, or null to show media items with alt text or without
     */
    public function __construct(
        public ?MediaFolderInterface $folder = null,
        public bool $unsorted = false,
        public ?string $search = null,
        public ?MediaKind $kind = null,
        public ?array $missingAltIn = null,
    ) {
    }
}
