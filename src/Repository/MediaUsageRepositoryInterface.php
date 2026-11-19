<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;

/**
 * Reads the media usage index. It's written when entries and editor presets are, by Media\MediaUsageListener
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
     * The editor preset's rows: the media items it uses
     *
     * @return list<MediaUsageInterface>
     */
    public function findByPreset(EditorPresetInterface $preset): array;

    /**
     * The uses of the media item with this UUID, one per entry and one per editor preset, with their entries and
     * presets: the entries' first, oldest entry first, then the presets', oldest preset first
     *
     * @return list<MediaUsageInterface>
     */
    public function findByMediaItem(string $uuid): array;

    /**
     * How many entries and editor presets use each of the media items with these UUIDs, in one query. One that nothing
     * uses is left out
     *
     * @param list<string> $uuids
     *
     * @return array<string, int> by the media item's UUID
     */
    public function countByMediaItems(array $uuids): array;
}
