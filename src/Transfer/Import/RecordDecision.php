<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What an import does with one record of the export, an entry or an editor preset, and the conflicts that stop it.
 * "Source wins" for picked records, unless the target's copy is newer, and records that only came along are created
 * when the target lacks them and otherwise left alone. A URL clash is found apart, for the records the import writes.
 * See "What an import does", "Conflicts" and "Stale records" in docs/spec/transfer.md and ADR 0016
 */
final readonly class RecordDecision
{
    /**
     * @param list<ConflictKind> $conflicts
     */
    public function __construct(
        public ImportAction $action,
        public array $conflicts = [],
    ) {
    }

    /**
     * A record identical to the target's is unchanged first, so it's never a conflict. A picked one the target has
     * otherwise is a conflict while the target has a draft of it or a scheduled publish, or changed it after the
     * export's copy
     *
     * @param bool $picked whether the export picked it, rather than it coming along as a dependency
     * @param \DateTimeImmutable $changedAt when it last changed in the source, as the export records it
     * @param TargetRecord|null $target what the target holds of it, or null when it has none
     */
    public static function of(bool $picked, \DateTimeImmutable $changedAt, ?TargetRecord $target): self
    {
        if (null === $target) {
            return new self(ImportAction::Create);
        }

        if ($target->identical) {
            return new self(ImportAction::Unchanged);
        }

        if (!$picked) {
            return new self(ImportAction::LeaveAlone);
        }

        $conflicts = [];
        if ($target->hasDraft) {
            $conflicts[] = ConflictKind::Draft;
        }

        if ($target->hasScheduledPublish) {
            $conflicts[] = ConflictKind::ScheduledPublish;
        }

        if ($target->changedAt > $changedAt) {
            $conflicts[] = ConflictKind::Stale;
        }

        return new self(ImportAction::Update, $conflicts);
    }

    /**
     * Whether the import writes the record: creates it, or updates it
     */
    public function writes(): bool
    {
        return $this->action->writes();
    }
}
