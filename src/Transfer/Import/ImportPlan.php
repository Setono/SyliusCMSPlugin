<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What an import would do, which a dry run shows and the admin renders before an import is confirmed: what's created,
 * updated, unchanged or left alone, by kind of record, the conflicts, the reasons it refuses, the homepage changes and
 * the references that don't resolve. See "What an import does" in docs/spec/transfer.md
 */
final readonly class ImportPlan
{
    /** @var array<string, list<PlannedRecord>> by kind, as RecordKind's values, in the order of RecordKind's cases */
    private array $records;

    /**
     * @param list<PlannedRecord> $records the records of every kind, those of each kind in the order they're written:
     *                                     the entries other entries use before what uses them, and media folders'
     *                                     parents before their children
     * @param list<ImportConflict> $conflicts
     * @param list<ImportRefusal> $refusals
     * @param list<HomepageChange> $homepageChanges
     * @param list<UnresolvedReference> $unresolvedReferences
     */
    public function __construct(
        array $records,
        public array $conflicts = [],
        public array $refusals = [],
        public array $homepageChanges = [],
        public array $unresolvedReferences = [],
    ) {
        $byKind = [];
        foreach (RecordKind::cases() as $kind) {
            $byKind[$kind->value] = [];
        }

        foreach ($records as $record) {
            $byKind[$record->kind->value][] = $record;
        }

        $this->records = $byKind;
    }

    /**
     * The plan with these reasons to refuse too, such as the publishes that failed when it was applied
     *
     * @param list<ImportRefusal> $refusals
     */
    public function withRefusals(array $refusals): self
    {
        return new self($this->records(), $this->conflicts, [...$this->refusals, ...$refusals], $this->homepageChanges, $this->unresolvedReferences);
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
     * The kinds it has records of, in the order of RecordKind's cases, which is the order it lists them in. Twig reads
     * them here, as the lowest Twig the plugin allows has no enum_cases()
     *
     * @return list<RecordKind>
     */
    public function kinds(): array
    {
        return array_values(array_filter(RecordKind::cases(), fn (RecordKind $kind): bool => [] !== $this->records[$kind->value]));
    }

    /**
     * @param RecordKind|null $kind null for every kind
     *
     * @return list<PlannedRecord> its records of the kind, or of every kind, kind by kind in the order of RecordKind's
     *                             cases
     */
    public function records(?RecordKind $kind = null): array
    {
        return null === $kind ? array_merge(...array_values($this->records)) : $this->records[$kind->value];
    }

    /**
     * @return list<PlannedRecord> the records of the kind the import writes: those it creates or updates
     */
    public function written(RecordKind $kind): array
    {
        return array_values(array_filter($this->records[$kind->value], static fn (PlannedRecord $record): bool => $record->writes()));
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
