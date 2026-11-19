<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * The media items of the media library. See docs/spec/media.md
 */
interface MediaItemRepositoryInterface
{
    /**
     * Stores the media item at once
     */
    public function add(MediaItemInterface $resource): void;

    public function findOneByUuid(string $uuid): ?MediaItemInterface;

    /**
     * The media items with these UUIDs, in one query, in no particular order. A UUID with none is left out
     *
     * @param list<string> $uuids
     *
     * @return list<MediaItemInterface>
     */
    public function findByUuids(array $uuids): array;

    /**
     * The UUIDs of these that a media item has, in one query, in no particular order
     *
     * @param list<string> $uuids
     *
     * @return list<string>
     */
    public function findExistingUuids(array $uuids): array;

    /**
     * The media item whose current file has this checksum, the one uploaded first when there are several
     */
    public function findOneByChecksum(string $checksum): ?MediaItemInterface;

    /**
     * The media item whose current file sits at this path in the plugin's storage
     */
    public function findOneByPath(string $path): ?MediaItemInterface;

    /**
     * Every media item, the one uploaded first first
     *
     * @return list<MediaItemInterface>
     */
    public function findAllInUploadOrder(): array;

    /**
     * The media items right in these folders, not in their subfolders, the one uploaded first first
     *
     * @param list<MediaFolderInterface> $folders
     *
     * @return list<MediaItemInterface>
     */
    public function findInFolders(array $folders): array;

    /**
     * The media items the library shows, newest first
     *
     * @return Pagerfanta<MediaItemInterface>
     */
    public function createLibraryPaginator(MediaFilter $filter): Pagerfanta;
}
