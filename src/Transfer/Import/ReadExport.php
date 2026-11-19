<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaFolder;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;

/**
 * An export as the import read it: its entries and editor presets upgraded, its media folders and items as they're
 * written, and the reasons to refuse it that reading found, such as code the target lacks
 */
final readonly class ReadExport
{
    /**
     * @param array<string, ReadEntry> $entries by UUID
     * @param array<string, ReadPreset> $presets by UUID
     * @param list<ExportedMediaFolder> $mediaFolders parents before their children
     * @param array<string, ExportedMediaItem> $mediaItems by UUID. Their files are #280's
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
}
