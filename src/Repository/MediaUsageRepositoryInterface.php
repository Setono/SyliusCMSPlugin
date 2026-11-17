<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;

/**
 * Reads the media usage index. It's written when entries are, by Media\MediaUsageListener
 */
interface MediaUsageRepositoryInterface
{
    /**
     * The entry's rows: the media items it uses
     *
     * @return list<MediaUsageInterface>
     */
    public function findByEntry(EntryInterface $entry): array;

    /**
     * The uses of the media item with this UUID, one per entry, with their entries, oldest entry first
     *
     * @return list<MediaUsageInterface>
     */
    public function findByMediaItem(string $uuid): array;

    /**
     * How many entries use each of the media items with these UUIDs, in one query. One that nothing uses is left out
     *
     * @param list<string> $uuids
     *
     * @return array<string, int> by the media item's UUID
     */
    public function countByMediaItems(array $uuids): array;
}
