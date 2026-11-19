<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * The folders an export's media/folders.json lists: every folder that holds an exported media item, with its parents.
 * Parents come before their children, and folders with the same parent are in name order, as the library lists them.
 * See "media/folders.json" in docs/spec/transfer.md
 *
 * @internal
 */
final class ExportedFolders
{
    private function __construct()
    {
    }

    /**
     * @param iterable<MediaItemInterface> $mediaItems
     *
     * @return list<MediaFolderInterface>
     */
    public static function of(iterable $mediaItems): array
    {
        $children = [];
        foreach ($mediaItems as $mediaItem) {
            for ($folder = $mediaItem->getFolder(); null !== $folder; $folder = $folder->getParent()) {
                $children[$folder->getParent()?->getUuid() ?? ''][$folder->getUuid()] = $folder;
            }
        }

        return self::below('', $children);
    }

    /**
     * @param array<string, array<string, MediaFolderInterface>> $children by their parent's UUID, '' for the top
     *
     * @return list<MediaFolderInterface>
     */
    private static function below(string $parent, array $children): array
    {
        $folders = array_values($children[$parent] ?? []);
        usort($folders, static fn (MediaFolderInterface $a, MediaFolderInterface $b): int => [$a->getName(), $a->getUuid()] <=> [$b->getName(), $b->getUuid()]);

        $listed = [];
        foreach ($folders as $folder) {
            $listed[] = $folder;
            array_push($listed, ...self::below($folder->getUuid(), $children));
        }

        return $listed;
    }
}
