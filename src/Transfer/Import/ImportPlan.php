<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What an import would do, which a dry run shows and the admin renders before an import is confirmed: what's created,
 * updated, unchanged or left alone, the conflicts, the reasons it refuses, the homepage changes and the references
 * that don't resolve. See "What an import does" in docs/spec/transfer.md. Media items (#280) join the records
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
     */
    public function __construct(
        public array $entries,
        public array $presets,
        public array $conflicts = [],
        public array $refusals = [],
        public array $homepageChanges = [],
        public array $unresolvedReferences = [],
    ) {
    }

    /**
     * The plan with these reasons to refuse too, such as the publishes that failed when it was applied
     *
     * @param list<ImportRefusal> $refusals
     */
    public function withRefusals(array $refusals): self
    {
        return new self($this->entries, $this->presets, $this->conflicts, [...$this->refusals, ...$refusals], $this->homepageChanges, $this->unresolvedReferences);
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
     * @return list<PlannedRecord> every record, entries first
     */
    public function records(): array
    {
        return [...$this->entries, ...$this->presets];
    }

    /**
     * @return list<PlannedRecord> the entries the import writes: those it creates or updates
     */
    public function writtenEntries(): array
    {
        return array_values(array_filter($this->entries, static fn (PlannedRecord $entry): bool => ImportAction::Create === $entry->action || ImportAction::Update === $entry->action));
    }

    /**
     * @return list<string> the UUIDs of the records with this action, entries first
     */
    public function uuids(ImportAction $action): array
    {
        return array_values(array_map(
            static fn (PlannedRecord $record): string => $record->uuid,
            array_filter($this->records(), static fn (PlannedRecord $record): bool => $action === $record->action),
        ));
    }

    /**
     * @return list<ImportConflict> the conflicts about the record
     */
    public function conflictsOf(string $uuid): array
    {
        return array_values(array_filter($this->conflicts, static fn (ImportConflict $conflict): bool => $uuid === $conflict->uuid));
    }
}
