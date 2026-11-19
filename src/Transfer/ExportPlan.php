<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * What an export holds: the records someone picked, and the snippets and media items that come along because the
 * picked records use them, their dependencies. A record that's picked is never also listed as a dependency. Every list
 * is sorted by UUID, so exports of the same records come out the same. See "What a transfer moves" in
 * docs/spec/transfer.md
 */
final readonly class ExportPlan
{
    /** @var list<EntryInterface> */
    private array $entries;

    /** @var list<EditorPresetInterface> */
    private array $presets;

    /** @var list<MediaItemInterface> */
    private array $mediaItems;

    /** @var array<string, true> */
    private array $picked;

    /**
     * @param iterable<EntryInterface> $entries the picked entries and the snippets they use
     * @param iterable<EditorPresetInterface> $presets the picked editor presets
     * @param iterable<MediaItemInterface> $mediaItems the picked media items and those the picked records use
     * @param iterable<string> $picked the UUIDs of the picked records
     */
    public function __construct(iterable $entries = [], iterable $presets = [], iterable $mediaItems = [], iterable $picked = [])
    {
        $this->entries = self::byUuid($entries);
        $this->presets = self::byUuid($presets);
        $this->mediaItems = self::byUuid($mediaItems);
        $this->picked = array_fill_keys([...$picked], true);
    }

    /**
     * Whether it holds no record at all
     */
    public function isEmpty(): bool
    {
        return [] === $this->entries && [] === $this->presets && [] === $this->mediaItems;
    }

    /**
     * Every entry it holds, picked or a dependency
     *
     * @return list<EntryInterface>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<EditorPresetInterface>
     */
    public function presets(): array
    {
        return $this->presets;
    }

    /**
     * Every media item it holds, picked or a dependency
     *
     * @return list<MediaItemInterface>
     */
    public function mediaItems(): array
    {
        return $this->mediaItems;
    }

    public function isPicked(string $uuid): bool
    {
        return isset($this->picked[$uuid]);
    }

    /**
     * @return list<string> UUIDs
     */
    public function pickedEntries(): array
    {
        return $this->pickedOf($this->entries);
    }

    /**
     * @return list<string> UUIDs
     */
    public function pickedPresets(): array
    {
        return $this->pickedOf($this->presets);
    }

    /**
     * @return list<string> UUIDs
     */
    public function pickedMediaItems(): array
    {
        return $this->pickedOf($this->mediaItems);
    }

    /**
     * The snippets that came along because the picked records use them
     *
     * @return list<EntryInterface>
     */
    public function addedEntries(): array
    {
        return array_values(array_filter($this->entries, fn (EntryInterface $entry): bool => !$this->isPicked($entry->getUuid())));
    }

    /**
     * The media items that came along because the picked records use them
     *
     * @return list<MediaItemInterface>
     */
    public function addedMediaItems(): array
    {
        return array_values(array_filter($this->mediaItems, fn (MediaItemInterface $mediaItem): bool => !$this->isPicked($mediaItem->getUuid())));
    }

    /**
     * @param list<EntryInterface|EditorPresetInterface|MediaItemInterface> $records
     *
     * @return list<string>
     */
    private function pickedOf(array $records): array
    {
        $uuids = [];
        foreach ($records as $record) {
            if ($this->isPicked($record->getUuid())) {
                $uuids[] = $record->getUuid();
            }
        }

        return $uuids;
    }

    /**
     * Each record once, sorted by UUID
     *
     * @template T of EntryInterface|EditorPresetInterface|MediaItemInterface
     *
     * @param iterable<T> $records
     *
     * @return list<T>
     */
    private static function byUuid(iterable $records): array
    {
        $byUuid = [];
        foreach ($records as $record) {
            $byUuid[$record->getUuid()] = $record;
        }

        ksort($byUuid, \SORT_STRING);

        return array_values($byUuid);
    }
}
