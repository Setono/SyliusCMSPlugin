<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use Doctrine\Persistence\ObjectManager;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Model\Import;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ConflictKind;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportConflict;
use Setono\SyliusCMSPlugin\Transfer\Import\ImporterInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportOptions;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResultNormalizer;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Setono\SyliusCMSPlugin\Transfer\Import\PlannedRecord;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordKind;
use Setono\SyliusCMSPlugin\Transfer\Import\RunImport;
use Setono\SyliusCMSPlugin\Transfer\Import\RunImportHandler;
use Setono\SyliusCMSPlugin\Transfer\Import\UploadedExports;
use Setono\SyliusCMSPlugin\Transfer\Import\ZipSource;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Translation\TranslatableMessage;

final class RunImportHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const PATH = 'imports/abc.zip';

    /** @var ObjectProphecy<ImporterInterface> */
    private ObjectProphecy $importer;

    /** @var ObjectProphecy<FilesystemOperator> */
    private ObjectProphecy $storage;

    /** @var ObjectProphecy<ImportRepositoryInterface> */
    private ObjectProphecy $imports;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->importer = $this->prophesize(ImporterInterface::class);
        $this->storage = $this->prophesize(FilesystemOperator::class);
        $this->storage->fileExists(self::PATH)->willReturn(true);
        $zip = $this->zip();
        // Prophecy binds a closure to the double, so it's static
        $this->storage->readStream(self::PATH)->will(static fn () => fopen($zip, 'rb'));
        $this->imports = $this->prophesize(ImportRepositoryInterface::class);
        $this->manager = $this->prophesize(ObjectManager::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @test
     */
    public function it_records_a_refused_import_as_its_result_and_returns_so_messenger_does_not_retry_it(): void
    {
        $import = $this->confirmed(overwrite: false, publish: true);
        $refused = ImportResult::refused(new ImportPlan(
            [new PlannedRecord(RecordKind::Entry, 'a1', 'Autumn sale', true, ImportAction::Update, 'page')],
            [],
            [new ImportConflict(ConflictKind::Draft, 'a1', new TranslatableMessage('setono_sylius_cms.import.conflict.draft', ['%title%' => 'Autumn sale']))],
        ));
        $this->importer->import(Argument::type(ZipSource::class), Argument::that(
            static fn (ImportOptions $options): bool => !$options->dryRun && $options->publish && !$options->overwrite,
        ))->willReturn($refused)->shouldBeCalledOnce();
        $this->storage->delete(self::PATH)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalled();

        $this->handler()(new RunImport($import->getUuid()));

        self::assertSame(ImportState::Finished, $import->getState());
        self::assertEquals($refused, (new ImportResultNormalizer())->denormalize($import->getResult() ?? []));
        self::assertNull($import->getPath());
        self::assertSame('2026-11-20T08:00:00+00:00', $import->getFinishedAt()?->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_records_an_applied_import_and_deletes_the_zip(): void
    {
        $import = $this->confirmed(overwrite: true, publish: false);
        $applied = ImportResult::applied(new ImportPlan([new PlannedRecord(RecordKind::Entry, 'a1', 'Autumn sale', true, ImportAction::Create, 'page')], []), false);
        $this->importer->import(Argument::type(ZipSource::class), Argument::that(
            static fn (ImportOptions $options): bool => !$options->dryRun && !$options->publish && $options->overwrite,
        ))->willReturn($applied);
        $this->storage->delete(self::PATH)->shouldBeCalledOnce();

        $this->handler()(new RunImport($import->getUuid()));

        self::assertSame(ImportState::Finished, $import->getState());
        self::assertSame(ImportStatus::Applied, (new ImportResultNormalizer())->denormalize($import->getResult() ?? [])->status);
    }

    /**
     * @test
     */
    public function it_runs_the_dry_run_of_an_uploaded_zip_as_if_overwriting_and_keeps_the_zip_for_the_admin_user_to_confirm(): void
    {
        $import = $this->uploaded();
        $wouldApply = ImportResult::applied(new ImportPlan([new PlannedRecord(RecordKind::Entry, 'a1', 'Autumn sale', true, ImportAction::Update, 'page')], []), true);
        $this->importer->import(Argument::type(ZipSource::class), Argument::that(
            static fn (ImportOptions $options): bool => $options->dryRun && !$options->publish && $options->overwrite,
        ))->willReturn($wouldApply);
        $this->storage->delete(Argument::any())->shouldNotBeCalled();

        $this->handler()(new RunImport($import->getUuid()));

        self::assertSame(ImportState::Checked, $import->getState());
        self::assertEquals($wouldApply, (new ImportResultNormalizer())->denormalize($import->getDryRun() ?? []));
        self::assertSame(self::PATH, $import->getPath());
    }

    /**
     * @test
     */
    public function it_finishes_an_import_whose_dry_run_refuses_even_overwriting_and_deletes_the_zip(): void
    {
        $import = $this->uploaded();
        $refused = ImportResult::refused(new ImportPlan([], []));
        $this->importer->import(Argument::cetera())->willReturn($refused);
        $this->storage->delete(self::PATH)->shouldBeCalledOnce();

        $this->handler()(new RunImport($import->getUuid()));

        self::assertSame(ImportState::Finished, $import->getState());
        self::assertNull($import->getDryRun());
        self::assertSame(ImportStatus::Refused, (new ImportResultNormalizer())->denormalize($import->getResult() ?? [])->status);
    }

    /**
     * @test
     */
    public function it_records_a_file_that_is_not_a_zip_as_an_export_it_cannot_read(): void
    {
        $import = $this->uploaded();
        $file = $this->file('not a zip');
        $this->storage->readStream(self::PATH)->will(static fn () => fopen($file, 'rb'));
        $this->importer->import(Argument::cetera())->shouldNotBeCalled();
        $this->storage->delete(self::PATH)->shouldBeCalledOnce();

        $this->handler()(new RunImport($import->getUuid()));

        $result = (new ImportResultNormalizer())->denormalize($import->getResult() ?? []);
        self::assertSame(ImportStatus::Invalid, $result->status);
        self::assertSame('cms-export.zip isn\'t a zip that can be read.', $result->invalid);
    }

    /**
     * @test
     */
    public function it_records_a_zip_no_longer_in_the_storage_as_an_export_it_cannot_read(): void
    {
        $import = $this->uploaded();
        $this->storage->fileExists(self::PATH)->willReturn(false);
        $this->storage->delete(self::PATH)->shouldBeCalled();
        $this->importer->import(Argument::cetera())->shouldNotBeCalled();

        $this->handler()(new RunImport($import->getUuid()));

        $result = (new ImportResultNormalizer())->denormalize($import->getResult() ?? []);
        self::assertSame(ImportStatus::Invalid, $result->status);
        self::assertSame('The uploaded cms-export.zip is no longer in the media storage.', $result->invalid);
    }

    /**
     * @test
     */
    public function it_leaves_an_unexpected_error_to_messenger_to_retry(): void
    {
        $import = $this->confirmed(overwrite: false, publish: false);
        $this->importer->import(Argument::cetera())->willThrow(new \RuntimeException('The database went away'));
        $this->storage->delete(Argument::any())->shouldNotBeCalled();

        try {
            $this->handler()(new RunImport($import->getUuid()));
            self::fail('The error is thrown');
        } catch (\RuntimeException $e) {
            self::assertSame('The database went away', $e->getMessage());
        }

        self::assertSame(ImportState::Importing, $import->getState());
        self::assertSame(self::PATH, $import->getPath());
        self::assertSame('2026-11-20T08:00:00+00:00', $import->getStartedAt()?->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_does_nothing_for_an_import_that_does_not_wait_for_the_worker(): void
    {
        $checked = $this->uploaded();
        $checked->check([]);
        $abandoned = $this->uploaded();
        $abandoned->abandon(new \DateTimeImmutable());
        $this->imports->findOneByUuid('0193a5be-0000-7000-8000-00000000dead')->willReturn(null);
        $this->importer->import(Argument::cetera())->shouldNotBeCalled();

        $this->handler()(new RunImport($checked->getUuid()));
        $this->handler()(new RunImport($abandoned->getUuid()));
        $this->handler()(new RunImport('0193a5be-0000-7000-8000-00000000dead'));

        self::assertSame(ImportState::Checked, $checked->getState());
        self::assertSame(ImportState::Abandoned, $abandoned->getState());
    }

    /**
     * @test
     */
    public function it_reads_the_import_again_after_running_it_and_leaves_one_abandoned_meanwhile_as_it_is(): void
    {
        $import = $this->uploaded();
        $abandonedMeanwhile = new Import();
        $abandonedMeanwhile->abandon(new \DateTimeImmutable());
        $this->imports->findOneByUuid($import->getUuid())->willReturn($import, $abandonedMeanwhile);
        $this->importer->import(Argument::cetera())->willReturn(ImportResult::applied(new ImportPlan([], []), true));

        $this->handler()(new RunImport($import->getUuid()));

        self::assertSame(ImportState::Abandoned, $abandonedMeanwhile->getState());
        self::assertNull($abandonedMeanwhile->getDryRun());
    }

    private function handler(): RunImportHandler
    {
        return new RunImportHandler(
            $this->imports->reveal(),
            $this->importer->reveal(),
            new ImportResultNormalizer(),
            new UploadedExports($this->storage->reveal(), new NullLogger()),
            $this->manager->reveal(),
            new MockClock('2026-11-20 08:00:00', 'UTC'),
        );
    }

    private function uploaded(): ImportInterface
    {
        $import = new Import();
        $import->setFilename('cms-export.zip');
        $import->setPath(self::PATH);
        $import->setCreatedAt(new \DateTimeImmutable('2026-11-20 07:59:00'));
        $this->imports->findOneByUuid($import->getUuid())->willReturn($import);

        return $import;
    }

    private function confirmed(bool $overwrite, bool $publish): ImportInterface
    {
        $import = $this->uploaded();
        $import->check([]);
        $import->confirm($overwrite, $publish);

        return $import;
    }

    /**
     * A zip holding a manifest, which the importer, a double here, would read
     */
    private function zip(): string
    {
        $file = $this->file('');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::OVERWRITE));
        $zip->addFromString('manifest.json', '{}');
        $zip->close();

        return $file;
    }

    private function file(string $content): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'setono_sylius_cms_test_');
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
