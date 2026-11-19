<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Picks the records a selection names, then follows what they use: the references the field type handlers mark as
 * dependencies, in an entry's own fields and its tree, and in an editor preset's blocks and starting point. A snippet
 * that comes along brings what it uses too, and a video brings its poster. References that don't resolve are left
 * out: an import reports them. See "What a transfer moves" in docs/spec/transfer.md
 */
final readonly class ExportPlanner implements ExportPlannerInterface
{
    public function __construct(
        private EntryRepositoryInterface $entryRepository,
        private EditorPresetRepositoryInterface $presetRepository,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaFolderRepositoryInterface $mediaFolderRepository,
        private ContentTypeRegistryInterface $contentTypes,
        private PublishedContent $publishedContent,
        private ReferenceFinder $referenceFinder,
    ) {
    }

    public function plan(ExportSelection $selection): ExportPlan
    {
        $entries = $this->pickedEntries($selection);
        $presets = $this->pickedPresets($selection);
        $mediaItems = $this->pickedMediaItems($selection);
        $picked = [...array_keys($entries), ...array_keys($presets), ...array_keys($mediaItems)];

        $dependencies = new Dependencies();
        foreach ($presets as $preset) {
            $dependencies->add($this->inPreset($preset));
        }

        // Snippets bring what they use too, round by round, until a round finds no snippet the plan lacks
        $uses = $entries;
        do {
            foreach ($uses as $entry) {
                $dependencies->add($this->referenceFinder->inEntry($this->publishedContent->of($entry), $this->contentTypes->getByEntry($entry)));
            }

            $uses = [];
            foreach ($this->entryRepository->findByUuids(array_values(array_diff($dependencies->snippets(), array_map(strval(...), array_keys($entries))))) as $snippet) {
                $entries[$snippet->getUuid()] = $uses[] = $snippet;
            }
        } while ([] !== $uses);

        foreach ($this->mediaItemRepository->findByUuids(array_values(array_diff($dependencies->mediaItems(), array_map(strval(...), array_keys($mediaItems))))) as $mediaItem) {
            $mediaItems[$mediaItem->getUuid()] = $mediaItem;
        }

        // A video's poster is a dependency of the video
        foreach ($mediaItems as $mediaItem) {
            $poster = $mediaItem->getPoster();
            if (null !== $poster) {
                $mediaItems[$poster->getUuid()] ??= $poster;
            }
        }

        return new ExportPlan($entries, $presets, $mediaItems, $picked);
    }

    /**
     * @return array<string, EntryInterface> by UUID
     */
    private function pickedEntries(ExportSelection $selection): array
    {
        $codes = $selection->everything ? array_map(static fn ($contentType): string => $contentType->code, $this->contentTypes->all()) : $selection->contentTypes;

        $entries = [];
        foreach ($codes as $code) {
            if (!$this->contentTypes->has($code)) {
                throw new InvalidExportSelectionException(sprintf('There is no content type "%s".', $code));
            }

            foreach ($this->entryRepository->findByContentType($code) as $entry) {
                $entries[$entry->getUuid()] = $entry;
            }
        }

        $uuids = self::lowercase($selection->entries);
        foreach ($this->entryRepository->findByUuids($uuids) as $entry) {
            $entries[$entry->getUuid()] = $entry;
        }

        foreach ($uuids as $uuid) {
            if (!isset($entries[$uuid])) {
                throw new InvalidExportSelectionException(sprintf('There is no entry with the UUID %s.', $uuid));
            }
        }

        return $entries;
    }

    /**
     * @return array<string, EditorPresetInterface> by UUID
     */
    private function pickedPresets(ExportSelection $selection): array
    {
        $presets = [];
        if ($selection->everything || $selection->allPresets) {
            foreach ($this->presetRepository->findAllInCreationOrder() as $preset) {
                $presets[$preset->getUuid()] = $preset;
            }
        }

        foreach (self::lowercase($selection->presets) as $uuid) {
            $presets[$uuid] ??= $this->presetRepository->findOneByUuid($uuid) ?? throw new InvalidExportSelectionException(sprintf('There is no editor preset with the UUID %s.', $uuid));
        }

        return $presets;
    }

    /**
     * Picking a media folder picks every media item in it and in its subfolders
     *
     * @return array<string, MediaItemInterface> by UUID
     */
    private function pickedMediaItems(ExportSelection $selection): array
    {
        $mediaItems = [];
        if ($selection->everything) {
            foreach ($this->mediaItemRepository->findAllInUploadOrder() as $mediaItem) {
                $mediaItems[$mediaItem->getUuid()] = $mediaItem;
            }

            return $mediaItems;
        }

        $picked = [];
        foreach (self::lowercase($selection->mediaFolders) as $uuid) {
            $picked[] = $this->mediaFolderRepository->findOneByUuid($uuid) ?? throw new InvalidExportSelectionException(sprintf('There is no media folder with the UUID %s.', $uuid));
        }

        if ([] === $picked) {
            return [];
        }

        $folders = array_values(array_filter(
            $this->mediaFolderRepository->findAllByName(),
            static fn (MediaFolderInterface $folder): bool => [] !== array_filter($picked, static fn (MediaFolderInterface $pickedFolder): bool => $pickedFolder->contains($folder)),
        ));

        foreach ($this->mediaItemRepository->findInFolders($folders) as $mediaItem) {
            $mediaItems[$mediaItem->getUuid()] = $mediaItem;
        }

        return $mediaItems;
    }

    /**
     * @return \Generator<FoundReference>
     */
    private function inPreset(EditorPresetInterface $preset): \Generator
    {
        $content = (new EditorPresetContent())->read($preset);
        $startingPoint = $content->startingPoint;

        return null === $startingPoint ? $this->referenceFinder->inBlocks($content->blocks) : $this->referenceFinder->inTree(new StoredTree($startingPoint->layout, $content->blocks), $startingPoint->contentType);
    }

    /**
     * @param list<string> $uuids
     *
     * @return list<string>
     */
    private static function lowercase(array $uuids): array
    {
        return array_values(array_unique(array_map(strtolower(...), $uuids)));
    }
}
