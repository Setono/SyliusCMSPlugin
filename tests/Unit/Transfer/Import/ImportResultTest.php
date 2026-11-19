<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ConflictKind;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportConflict;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Setono\SyliusCMSPlugin\Transfer\Import\PlannedRecord;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordKind;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * An import's result, and the exit code setono:cms:import exits with for it. See "Commands" in docs/spec/transfer.md
 */
final class ImportResultTest extends TestCase
{
    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    /**
     * @test
     */
    public function it_exits_with_0_when_it_applied_or_the_dry_run_would(): void
    {
        self::assertSame(0, ImportResult::applied(self::plan(), dryRun: false)->exitCode());
        self::assertSame(ImportStatus::Applied, ImportResult::applied(self::plan(), dryRun: false)->status);
        self::assertSame(0, ImportResult::applied(self::plan(), dryRun: true)->exitCode());
        self::assertSame(ImportStatus::WouldApply, ImportResult::applied(self::plan(), dryRun: true)->status);
    }

    /**
     * @test
     */
    public function it_exits_with_2_when_what_it_read_is_not_an_export_it_reads(): void
    {
        $result = ImportResult::invalid('The export has the format 2, but this version of the plugin reads format 1');

        self::assertSame(2, $result->exitCode());
        self::assertSame(ImportStatus::Invalid, $result->status);
        self::assertNull($result->plan);
    }

    /**
     * @test
     */
    public function it_exits_with_3_when_it_refused_only_because_of_conflicts_overwriting_resolves(): void
    {
        $plan = self::plan(conflicts: [self::conflict(ConflictKind::Draft), self::conflict(ConflictKind::ScheduledPublish), self::conflict(ConflictKind::Stale)]);

        self::assertSame(3, ImportResult::refused($plan)->exitCode());
        self::assertSame(ImportStatus::Refused, ImportResult::refused($plan)->status);
    }

    /**
     * @test
     */
    public function it_exits_with_1_when_it_refused_for_a_reason_overwriting_does_not_fix(): void
    {
        self::assertSame(1, ImportResult::refused(self::plan(conflicts: [self::conflict(ConflictKind::Stale), self::conflict(ConflictKind::UrlClash)]))->exitCode());
        self::assertSame(1, ImportResult::refused(self::plan(refusals: [new ImportRefusal(self::PAGE, new TranslatableMessage('setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn sale', '%type%' => 'countdown']))]))->exitCode());
        self::assertSame(1, ImportResult::refused(self::plan(
            conflicts: [self::conflict(ConflictKind::Draft)],
            refusals: [new ImportRefusal(null, new TranslatableMessage('setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn sale', '%type%' => 'countdown']))],
        ))->exitCode());
    }

    /**
     * @test
     */
    public function it_lists_the_records_it_created_updated_and_left_unchanged(): void
    {
        $result = ImportResult::applied(self::plan(), dryRun: false);

        self::assertSame([self::SNIPPET], $result->created());
        self::assertSame([self::PAGE, self::PRESET], $result->updated());
        self::assertSame([], $result->unchanged());
    }

    /**
     * @test
     */
    public function it_is_refused_while_a_conflict_blocks_it_unless_overwriting_resolves_each(): void
    {
        $plan = self::plan(conflicts: [self::conflict(ConflictKind::Draft)]);
        self::assertTrue($plan->isRefused(overwrite: false));
        self::assertFalse($plan->isRefused(overwrite: true));

        self::assertTrue(self::plan(conflicts: [self::conflict(ConflictKind::UrlClash)])->isRefused(overwrite: true));
        self::assertTrue(self::plan(refusals: [new ImportRefusal(null, new TranslatableMessage('setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn sale', '%type%' => 'countdown']))])->isRefused(overwrite: true));
        self::assertFalse(self::plan()->isRefused(overwrite: false));
    }

    /**
     * @param list<ImportConflict> $conflicts
     * @param list<ImportRefusal> $refusals
     */
    private static function plan(array $conflicts = [], array $refusals = []): ImportPlan
    {
        return new ImportPlan(
            [
                new PlannedRecord(RecordKind::Entry, self::PAGE, 'Autumn sale', true, ImportAction::Update, 'page'),
                new PlannedRecord(RecordKind::Entry, self::SNIPPET, 'Autumn banner', false, ImportAction::Create, 'snippet'),
                new PlannedRecord(RecordKind::Preset, self::PRESET, 'Autumn hero', true, ImportAction::Update),
            ],
            $conflicts,
            $refusals,
        );
    }

    private static function conflict(ConflictKind $kind): ImportConflict
    {
        return new ImportConflict($kind, self::PAGE, new TranslatableMessage('setono_sylius_cms.import.conflict.' . $kind->value, ['%title%' => 'Autumn sale']));
    }
}
