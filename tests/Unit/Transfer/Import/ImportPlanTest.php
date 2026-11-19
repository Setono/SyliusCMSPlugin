<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Transfer\Import\PlannedRecord;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordKind;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * See "What an import does" in docs/spec/transfer.md
 */
final class ImportPlanTest extends TestCase
{
    /**
     * @test
     */
    public function it_lists_its_records_by_kind_in_the_order_it_was_given_them(): void
    {
        $plan = self::plan();

        self::assertSame(['a1', 'b2'], self::uuids($plan->records(RecordKind::Entry)));
        self::assertSame(['c3'], self::uuids($plan->records(RecordKind::Preset)));
        self::assertSame(['d4', 'e5'], self::uuids($plan->records(RecordKind::MediaItem)));
        self::assertSame([], $plan->records(RecordKind::MediaFolder));
    }

    /**
     * @test
     */
    public function it_lists_every_record_kind_by_kind_in_the_order_of_the_kinds(): void
    {
        self::assertSame(['a1', 'b2', 'c3', 'd4', 'e5'], self::uuids(self::plan()->records()));
    }

    /**
     * @test
     */
    public function it_lists_the_kinds_it_has_records_of_in_the_order_of_the_kinds(): void
    {
        self::assertSame([RecordKind::Entry, RecordKind::Preset, RecordKind::MediaItem], self::plan()->kinds());
        self::assertSame([], (new ImportPlan([]))->kinds());
    }

    /**
     * @test
     */
    public function it_lists_the_records_of_a_kind_it_writes(): void
    {
        $plan = self::plan();

        self::assertSame(['b2'], self::uuids($plan->written(RecordKind::Entry)));
        self::assertSame([], $plan->written(RecordKind::Preset));
        self::assertSame(['e5'], self::uuids($plan->written(RecordKind::MediaItem)));
    }

    /**
     * @test
     */
    public function it_lists_the_uuids_of_the_records_with_an_action_in_the_order_of_its_records(): void
    {
        self::assertSame(['c3', 'd4'], self::plan()->uuids(ImportAction::Unchanged));
    }

    /**
     * @test
     */
    public function it_keeps_its_records_when_it_gets_more_reasons_to_refuse(): void
    {
        $plan = self::plan();
        $refusal = new ImportRefusal('b2', new TranslatableMessage('setono_sylius_cms.import.embed_code', ['%title%' => 'Autumn banner']));

        $refused = $plan->withRefusals([$refusal]);

        self::assertSame([$refusal], $refused->refusals);
        self::assertSame($plan->records(), $refused->records());
        self::assertTrue($refused->isRefused(overwrite: true));
    }

    /**
     * The records of the kinds out of the kinds' order, as a planner may add them
     */
    private static function plan(): ImportPlan
    {
        return new ImportPlan([
            new PlannedRecord(RecordKind::MediaItem, 'd4', 'Banner', false, ImportAction::Unchanged),
            new PlannedRecord(RecordKind::Entry, 'a1', 'Autumn sale', true, ImportAction::LeaveAlone, 'page'),
            new PlannedRecord(RecordKind::Preset, 'c3', 'Autumn hero', true, ImportAction::Unchanged),
            new PlannedRecord(RecordKind::Entry, 'b2', 'Autumn banner', false, ImportAction::Create, 'snippet'),
            new PlannedRecord(RecordKind::MediaItem, 'e5', 'Poster', true, ImportAction::Update),
        ]);
    }

    /**
     * @param list<PlannedRecord> $records
     *
     * @return list<string>
     */
    private static function uuids(array $records): array
    {
        return array_map(static fn (PlannedRecord $record): string => $record->uuid, $records);
    }
}
