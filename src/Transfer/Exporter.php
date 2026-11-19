<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedEntry;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaFolder;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;

/**
 * Writes the manifest, then each entry with its on-save settings and a snippet's placements, each editor preset, the
 * media folders and each media item with its original file from the plugin's storage. An entry is its published
 * content, or its rows if it was never published: its draft never travels, and nor do revisions, share links or old
 * slugs. See "Export format" in docs/spec/transfer.md
 */
final readonly class Exporter implements ExporterInterface
{
    /**
     * @param string|null $pluginVersion the plugin version the manifest names, or null for the one that's installed
     */
    public function __construct(
        private EntryNormalizer $entryNormalizer,
        private PublishedContent $publishedContent,
        private PlacementRepositoryInterface $placementRepository,
        private FilesystemOperator $storage,
        private ClockInterface $clock,
        private ?string $pluginVersion = null,
    ) {
    }

    public function export(ExportPlan $plan, ExportTargetInterface $target): void
    {
        $format = new ExportFormat();

        $target->write(ExportFormat::MANIFEST, $format->encodeManifest(new Manifest(
            $this->pluginVersion ?? PluginVersion::installed(),
            $this->clock->now(),
            $plan->pickedEntries(),
            $plan->pickedPresets(),
            $plan->pickedMediaItems(),
        )));

        foreach ($plan->entries() as $entry) {
            $target->write(ExportFormat::entryPath($entry->getUuid()), $format->encodeEntry($this->exportedEntry($entry)));
        }

        $presetContent = new EditorPresetContent();
        foreach ($plan->presets() as $preset) {
            $target->write(ExportFormat::presetPath($preset->getUuid()), $format->encodePreset($presetContent->read($preset)));
        }

        $target->write(ExportFormat::MEDIA_FOLDERS, $format->encodeMediaFolders(array_map(ExportedMediaFolder::of(...), ExportedFolders::of($plan->mediaItems()))));

        foreach ($plan->mediaItems() as $mediaItem) {
            $exported = ExportedMediaItem::of($mediaItem);
            $target->write(ExportFormat::mediaItemPath($exported->uuid), $format->encodeMediaItem($exported));

            $file = $this->storage->readStream($mediaItem->getPath());

            try {
                $target->writeStream(ExportFormat::mediaFilePath($exported->uuid, $exported->filename), $file);
            } finally {
                if (is_resource($file)) {
                    fclose($file);
                }
            }
        }
    }

    private function exportedEntry(EntryInterface $entry): ExportedEntry
    {
        $placements = $entry instanceof SnippetInterface ? $this->placementRepository->findBySnippet($entry) : [];
        $preceding = [] === $placements ? [] : PrecedingPlacements::of($this->placementRepository->findBySlots(array_values(array_unique(array_map(
            static fn (PlacementInterface $placement): string => $placement->getSlot(),
            $placements,
        )))));

        return new ExportedEntry(
            $entry->getChangedAt(),
            EntrySettings::of($entry),
            array_map(static fn (PlacementInterface $placement): ExportedPlacement => new ExportedPlacement(
                $placement->getUuid(),
                $placement->getSlot(),
                $placement->getPageScope(),
                $preceding[$placement->getUuid()] ?? [],
            ), $placements),
            $this->entryNormalizer->normalize($this->publishedContent->of($entry)),
        );
    }
}
