<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaFolder;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;

/**
 * An export as the import read it: its entries and editor presets upgraded, its media folders as they're written, its
 * media items with their files checked, and the reasons to refuse it that reading found, such as code the target lacks
 * or a file that fails its checks. Its media files wait in temporary copies until cleanUp()
 */
final readonly class ReadExport
{
    /**
     * @param array<string, ReadEntry> $entries by UUID
     * @param array<string, ReadPreset> $presets by UUID
     * @param list<ExportedMediaFolder> $mediaFolders parents before their children
     * @param array<string, ReadMediaItem> $mediaItems by UUID
     * @param list<ImportRefusal> $refusals
     */
    public function __construct(
        public Manifest $manifest,
        public array $entries,
        public array $presets,
        public array $mediaFolders = [],
        public array $mediaItems = [],
        public array $refusals = [],
    ) {
    }

    /**
     * Removes the temporary copies of its media files, once the import is done with them
     */
    public function cleanUp(): void
    {
        foreach ($this->mediaItems as $mediaItem) {
            $mediaItem->cleanUp();
        }
    }
}
