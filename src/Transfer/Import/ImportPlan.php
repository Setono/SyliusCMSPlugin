<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What an import would do, which a dry run shows and the admin renders before an import is confirmed: what's created,
 * updated, unchanged or left alone, the conflicts, the reasons it refuses, the homepage changes and the references
 * that don't resolve. See "What an import does" in docs/spec/transfer.md
 */
final readonly class ImportPlan
{
    /**
     * @param list<PlannedRecord> $entries in the order they're written: what other entries use before what uses them
     * @param list<PlannedRecord> $presets
     * @param list<ImportConflict> $conflicts
     * @param list<ImportRefusal> $refusals
     * @param list<HomepageChange> $homepageChanges
     * @param list<UnresolvedReference> $unresolvedReferences
     * @param list<PlannedRecord> $mediaItems
     * @param list<PlannedRecord> $mediaFolders the folders of the media items it writes, with their parents: parents
     *                                         before their children
     */
    public function __construct(
        public array $entries,
        public array $presets,
        public array $conflicts = [],
        public array $refusals = [],
        public array $homepageChanges = [],
        public array $unresolvedReferences = [],
        public array $mediaItems = [],
        public array $mediaFolders = [],
    ) {
    }

    /**
     * The plan with these reasons to refuse too, such as the publishes that failed when it was applied
     *
     * @param list<ImportRefusal> $refusals
     */
    public function withRefusals(array $refusals): self
    {
        return new self($this->entries, $this->presets, $this->conflicts, [...$this->refusals, ...$refusals], $this->homepageChanges, $this->unresolvedReferences, $this->mediaItems, $this->mediaFolders);
    }

    /**
     * Whether the import refuses: for a reason overwriting doesn't fix, or a conflict it doesn't resolve or wasn't asked to
     *
     * @param bool $overwrite whether the records the plan lists as conflicts are to be overwritten
     */
    public function isRefused(bool $overwrite): bool
    {
        if ([] !== $this->refusals) {
            return true;
        }

        foreach ($this->conflicts as $conflict) {
            if (!$overwrite || !$conflict->kind->isResolvedByOverwrite()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether only conflicts that overwriting resolves stand in the way
     */
    public function isRefusedOnlyForConflictsOverwritingResolves(): bool
    {
        if ([] !== $this->refusals || [] === $this->conflicts) {
            return false;
        }

        foreach ($this->conflicts as $conflict) {
            if (!$conflict->kind->isResolvedByOverwrite()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<PlannedRecord> every record: entries, editor presets, media items and media folders
     */
    public function records(): array
    {
        return [...$this->entries, ...$this->presets, ...$this->mediaItems, ...$this->mediaFolders];
    }

    /**
     * @return list<PlannedRecord> the entries the import writes: those it creates or updates
     */
    public function writtenEntries(): array
    {
        return array_values(array_filter($this->entries, static fn (PlannedRecord $entry): bool => $entry->writes()));
    }

    /**
     * @return list<PlannedRecord> the media items the import writes: those it creates or updates
     */
    public function writtenMediaItems(): array
    {
        return array_values(array_filter($this->mediaItems, static fn (PlannedRecord $mediaItem): bool => $mediaItem->writes()));
    }

    /**
     * @return list<string> the UUIDs of the records with this action, in the order of records()
     */
    public function uuids(ImportAction $action): array
    {
        return array_values(array_map(
            static fn (PlannedRecord $record): string => $record->uuid,
            array_filter($this->records(), static fn (PlannedRecord $record): bool => $action === $record->action),
        ));
    }
}
