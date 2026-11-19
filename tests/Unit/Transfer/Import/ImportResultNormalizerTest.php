<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ConflictKind;
use Setono\SyliusCMSPlugin\Transfer\Import\HomepageChange;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportConflict;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResultNormalizer;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Setono\SyliusCMSPlugin\Transfer\Import\PlannedRecord;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordKind;
use Setono\SyliusCMSPlugin\Transfer\Import\UnresolvedReference;
use Symfony\Component\Translation\TranslatableMessage;

final class ImportResultNormalizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_writes_a_refused_import_with_its_whole_plan_as_json_and_reads_it_back(): void
    {
        $result = ImportResult::refused(new ImportPlan(
            [
                new PlannedRecord(RecordKind::Entry, 'a1', 'Autumn sale', true, ImportAction::Update, 'page'),
                new PlannedRecord(RecordKind::Entry, 'b2', 'Autumn banner', false, ImportAction::LeaveAlone, 'snippet'),
            ],
            [new PlannedRecord(RecordKind::Preset, 'c3', 'Autumn hero', true, ImportAction::Create)],
            [new ImportConflict(ConflictKind::Draft, 'a1', new TranslatableMessage('setono_sylius_cms.import.conflict.draft', ['%title%' => 'Autumn sale']))],
            [
                new ImportRefusal('d4', new TranslatableMessage('setono_sylius_cms.import.media_refused', [
                    '%name%' => 'Banner',
                    '%reason%' => new TranslatableMessage('setono_sylius_cms.block.unknown_type', ['%type%' => 'banner'], 'validators'),
                ])),
                new ImportRefusal(null, new TranslatableMessage('setono_sylius_cms.import.path_outside_root', ['%path%' => '../evil'])),
            ],
            [new HomepageChange('WEB', 'e5', 'Home', 'a1', 'Autumn sale'), new HomepageChange('MOBILE', 'a1', 'Autumn sale', null, null)],
            [new UnresolvedReference('a1', 'Autumn sale', UnresolvedReference::CHANNEL, 'MOBILE')],
            [new PlannedRecord(RecordKind::MediaItem, 'd4', 'Banner', false, ImportAction::Create)],
            [new PlannedRecord(RecordKind::MediaFolder, 'f6', 'Campaigns', false, ImportAction::Unchanged)],
        ));
        $normalizer = new ImportResultNormalizer();

        $data = $normalizer->normalize($result);

        // What it writes is plain JSON
        self::assertSame($data, json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
        self::assertSame('refused', $data['status']);
        self::assertEquals($result, $normalizer->denormalize($data));
    }

    /**
     * @test
     */
    public function it_writes_an_applied_import_and_a_dry_run_that_would_apply(): void
    {
        $plan = new ImportPlan([new PlannedRecord(RecordKind::Entry, 'a1', 'Autumn sale', true, ImportAction::Create, 'page')], []);
        $normalizer = new ImportResultNormalizer();

        $applied = $normalizer->denormalize($normalizer->normalize(ImportResult::applied($plan, false)));
        $wouldApply = $normalizer->denormalize($normalizer->normalize(ImportResult::applied($plan, true)));

        self::assertSame(ImportStatus::Applied, $applied->status);
        self::assertEquals($plan, $applied->plan);
        self::assertSame(ImportStatus::WouldApply, $wouldApply->status);
        self::assertSame(['a1'], $wouldApply->created());
    }

    /**
     * @test
     */
    public function it_writes_an_export_it_cannot_read_with_the_reason_and_no_plan(): void
    {
        $normalizer = new ImportResultNormalizer();

        $invalid = $normalizer->denormalize($normalizer->normalize(ImportResult::invalid('cms-export.zip isn\'t a zip that can be read')));

        self::assertSame(ImportStatus::Invalid, $invalid->status);
        self::assertNull($invalid->plan);
        self::assertSame('cms-export.zip isn\'t a zip that can be read', $invalid->invalid);
    }

    /**
     * @test
     */
    public function it_refuses_to_read_what_it_did_not_write(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ImportResultNormalizer())->denormalize(['status' => 'applied', 'plan' => ['entries' => [['kind' => 'entry']]]]);
    }
}
