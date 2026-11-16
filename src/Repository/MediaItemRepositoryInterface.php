<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Media\MediaFilter;
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
     * The media item whose current file has this checksum, the one uploaded first when there are several
     */
    public function findOneByChecksum(string $checksum): ?MediaItemInterface;

    /**
     * The media items the library shows, newest first
     *
     * @return Pagerfanta<MediaItemInterface>
     */
    public function createLibraryPaginator(MediaFilter $filter): Pagerfanta;
}
