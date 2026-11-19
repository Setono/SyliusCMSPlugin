<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;
use Setono\SyliusCMSPlugin\Transfer\PublishedContent;

/**
 * What the target holds of a record of the export, for RecordDecision. An entry is identical to the export's when its
 * published content, or its rows if it was never published, its on-save settings and a snippet's placements are: its
 * draft and createdAt, which aren't exported, don't count, and nor does where a placement sits in its slot. A preset is
 * identical when its content is, whenever it changed
 *
 * @internal
 */
final readonly class TargetRecords
{
    public function __construct(
        private EntryNormalizer $entryNormalizer,
        private PublishedContent $publishedContent,
        private WorkingCopyManagerInterface $workingCopies,
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private PlacementRepositoryInterface $placements,
    ) {
    }

    public function ofEntry(ReadEntry $entry, EntryInterface $target): TargetRecord
    {
        $identical = $this->hasContentOf($target, $entry) &&
            [] === $entry->exported->settings->changesFrom(EntrySettings::of($target)) &&
            $this->targetPlacements($target) === self::placements($entry->exported->placements);

        return new TargetRecord(
            $target->getChangedAt(),
            $identical,
            $this->workingCopies->hasDraft($target),
            null !== $this->scheduledPublishes->findOneByEntry($target),
        );
    }

    /**
     * Whether the target's published content, or its rows if it was never published, is what the export holds, so the
     * import leaves its content as it is and writes no draft
     */
    public function hasContentOf(EntryInterface $target, ReadEntry $entry): bool
    {
        return $this->content($target) === $this->content($entry->content);
    }

    public function ofPreset(ReadPreset $preset, EditorPresetInterface $target): TargetRecord
    {
        try {
            $identical = self::presetContent((new EditorPresetContent())->read($target)) === self::presetContent($preset->preset);
        } catch (InvalidPresetException) {
            $identical = false;
        }

        return new TargetRecord($target->getChangedAt(), $identical);
    }

    /**
     * The entry's published content in the entry format, upgraded, without createdAt
     */
    private function content(EntryInterface $entry): string
    {
        $content = $this->entryNormalizer->normalize($this->publishedContent->of($entry));

        return CanonicalJson::encode(new ListedObject(array_diff_key($content->properties, ['createdAt' => true])));
    }

    /**
     * A snippet's placements, each its UUID, slot and page scope, in UUID order
     */
    private function targetPlacements(EntryInterface $target): string
    {
        if (!$target instanceof SnippetInterface) {
            return self::placements([]);
        }

        return self::placements(array_map(
            static fn (PlacementInterface $placement): ExportedPlacement => new ExportedPlacement($placement->getUuid(), $placement->getSlot(), $placement->getPageScope(), []),
            $this->placements->findBySnippet($target),
        ));
    }

    /**
     * @param list<ExportedPlacement> $placements
     */
    private static function placements(array $placements): string
    {
        $normalized = [];
        foreach ($placements as $placement) {
            $normalized[$placement->uuid] = [$placement->slot, $placement->scope];
        }

        ksort($normalized, \SORT_STRING);

        return CanonicalJson::encode($normalized);
    }

    /**
     * The preset in the preset file format, whenever it changed
     */
    private static function presetContent(Preset $preset): string
    {
        return CanonicalJson::encode((new PresetSerializer())->normalize(new Preset(
            $preset->name,
            $preset->blocks,
            $preset->category,
            $preset->description,
            $preset->startingPoint,
            uuid: $preset->uuid,
        )));
    }
}
