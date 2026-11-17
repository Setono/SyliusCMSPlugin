<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Setono\SyliusCMSPlugin\Field\MediaItemReferences;
use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;

/**
 * Searches the documents and videos of the media library, which the media link target links to by their permanent
 * URLs, as the library searches them: by name, original filename and alt text, newest first. Images have no permanent
 * URL, so it leaves them out. Each is named by its name
 *
 * @internal
 */
final readonly class MediaSearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    private const KINDS = [MediaKind::Document, MediaKind::Video];

    public function __construct(private MediaItemRepositoryInterface $mediaItemRepository)
    {
    }

    public function search(string $query, string $localeCode): array
    {
        $paginator = $this->mediaItemRepository->createLibraryPaginator(new MediaFilter(search: $query, kinds: self::KINDS));
        $paginator->setMaxPerPage(self::LIMIT);

        $results = [];
        foreach ($paginator->getCurrentPageResults() as $mediaItem) {
            $results[] = self::result($mediaItem);
        }

        return $results;
    }

    public function find(array $refs, string $localeCode): array
    {
        $uuids = array_values(array_unique(array_map(strtolower(...), array_filter($refs, MediaItemReferences::isUuid(...)))));
        if ([] === $uuids) {
            return [];
        }

        return array_values(array_map(
            self::result(...),
            array_filter($this->mediaItemRepository->findByUuids($uuids), static fn (MediaItemInterface $mediaItem): bool => in_array($mediaItem->getKind(), self::KINDS, true)),
        ));
    }

    private static function result(MediaItemInterface $mediaItem): SearchResult
    {
        return new SearchResult($mediaItem->getUuid(), $mediaItem->getName());
    }
}
