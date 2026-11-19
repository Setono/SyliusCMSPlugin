<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ConflictKind;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordDecision;
use Setono\SyliusCMSPlugin\Transfer\Import\TargetRecord;

/**
 * What an import plans for one record of an export, an entry or an editor preset, from what the target holds of it.
 * See "What an import does", "Conflicts" and "Stale records" in docs/spec/transfer.md
 */
final class RecordDecisionTest extends TestCase
{
    private const EXPORTED_AT = '2026-11-12 10:15:00';

    /**
     * @test
     */
    public function it_creates_a_record_the_target_lacks_whether_it_is_picked_or_came_along(): void
    {
        foreach ([true, false] as $picked) {
            $decision = RecordDecision::of($picked, self::changedAt(), null);

            self::assertSame(ImportAction::Create, $decision->action);
            self::assertSame([], $decision->conflicts);
        }
    }

    /**
     * @test
     */
    public function it_updates_a_picked_record_the_target_has_differently(): void
    {
        $decision = RecordDecision::of(true, self::changedAt(), new TargetRecord(self::changedAt('-1 day'), identical: false));

        self::assertSame(ImportAction::Update, $decision->action);
        self::assertSame([], $decision->conflicts);
        self::assertTrue($decision->writes());
    }

    /**
     * @test
     */
    public function it_leaves_a_record_that_came_along_as_the_target_has_it(): void
    {
        // A shared snippet the target edited stays as it is, however much newer its copy is
        $decision = RecordDecision::of(false, self::changedAt(), new TargetRecord(self::changedAt('+1 day'), identical: false, hasDraft: true));

        self::assertSame(ImportAction::LeaveAlone, $decision->action);
        self::assertSame([], $decision->conflicts);
        self::assertFalse($decision->writes());
    }

    /**
     * @test
     */
    public function it_marks_a_record_identical_to_the_targets_unchanged_before_it_checks_for_a_stale_one(): void
    {
        $decision = RecordDecision::of(true, self::changedAt(), new TargetRecord(self::changedAt('+1 day'), identical: true, hasDraft: true, hasScheduledPublish: true));

        self::assertSame(ImportAction::Unchanged, $decision->action);
        self::assertSame([], $decision->conflicts);
        self::assertFalse($decision->writes());
    }

    /**
     * @test
     */
    public function it_finds_a_picked_record_stale_when_the_target_changed_it_after_the_exports_copy(): void
    {
        self::assertSame([ConflictKind::Stale], RecordDecision::of(true, self::changedAt(), new TargetRecord(self::changedAt('+1 second'), identical: false))->conflicts);
        self::assertSame([], RecordDecision::of(true, self::changedAt(), new TargetRecord(self::changedAt(), identical: false))->conflicts);
    }

    /**
     * @test
     */
    public function it_finds_a_conflict_in_a_picked_entrys_draft_and_its_scheduled_publish_in_the_target(): void
    {
        $decision = RecordDecision::of(true, self::changedAt(), new TargetRecord(self::changedAt('+1 hour'), identical: false, hasDraft: true, hasScheduledPublish: true));

        self::assertSame(ImportAction::Update, $decision->action);
        self::assertSame([ConflictKind::Draft, ConflictKind::ScheduledPublish, ConflictKind::Stale], $decision->conflicts);
        foreach ($decision->conflicts as $conflict) {
            self::assertTrue($conflict->isResolvedByOverwrite(), $conflict->value);
        }

        self::assertFalse(ConflictKind::UrlClash->isResolvedByOverwrite());
    }

    private static function changedAt(string $modifier = '+0 seconds'): \DateTimeImmutable
    {
        return (new \DateTimeImmutable(self::EXPORTED_AT, new \DateTimeZone('UTC')))->modify($modifier);
    }
}
