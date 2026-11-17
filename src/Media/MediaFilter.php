<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * What the media library shows: a folder (All media unless one is given, or Unsorted), the search and the filters.
 * See "Search and filters" in docs/spec/media.md
 */
final readonly class MediaFilter
{
    /**
     * @param string|null $search matches the name, the original filename and the alt text in every locale
     * @param list<MediaKind> $kinds the kinds to show, or none to show every kind. The builder's media picker shows only
     *                               the kinds its field takes
     * @param list<string>|null $missingAltIn the store's locales, to show only images without alt text in one or more of
     *                                        them, or null to show media items with alt text or without
     * @param list<string>|null $uuids only the media items with these UUIDs, such as those a field holds, or null for any
     * @param bool $unused only the media items with no use in the usage index
     */
    public function __construct(
        public ?MediaFolderInterface $folder = null,
        public bool $unsorted = false,
        public ?string $search = null,
        public array $kinds = [],
        public ?array $missingAltIn = null,
        public ?array $uuids = null,
        public bool $unused = false,
    ) {
    }
}
