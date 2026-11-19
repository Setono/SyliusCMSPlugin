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
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\AbandonImport;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportQueue;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Setono\SyliusCMSPlugin\Transfer\Import\RunImport;
use Setono\SyliusCMSPlugin\Transfer\Import\UploadedExports;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class ImportQueueTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FilesystemOperator> */
    private ObjectProphecy $storage;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    /** @var list<Envelope> */
    private array $dispatched = [];

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->storage = $this->prophesize(FilesystemOperator::class);
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
    public function it_keeps_an_uploaded_export_zip_records_the_import_and_has_the_worker_check_it_and_abandon_it_in_a_day(): void
    {
        $this->storage->writeStream(Argument::containingString('imports/'), Argument::any(), ['visibility' => 'private'])->shouldBeCalledOnce();
        $this->manager->persist(Argument::type(ImportInterface::class))->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $import = $this->queue()->upload(new \SplFileInfo($this->zip(['manifest.json' => '{}'])), 'cms-export.zip');

        self::assertSame('cms-export.zip', $import->getFilename());
        self::assertStringStartsWith('imports/', (string) $import->getPath());
        self::assertSame('2026-11-20T08:00:00+00:00', $import->getCreatedAt()->format(\DATE_ATOM));
        self::assertSame(ImportState::Checking, $import->getState());
        self::assertCount(2, $this->dispatched);
        self::assertEquals(new RunImport($import->getUuid()), $this->dispatched[0]->getMessage());
        self::assertEquals(new AbandonImport($import->getUuid()), $this->dispatched[1]->getMessage());
        self::assertSame(86_400_000, $this->dispatched[1]->last(DelayStamp::class)?->getDelay());
    }

    /**
     * @test
     */
    public function it_refuses_a_zip_without_an_exports_manifest_and_keeps_nothing(): void
    {
        $this->storage->writeStream(Argument::cetera())->shouldNotBeCalled();
        $this->manager->persist(Argument::any())->shouldNotBeCalled();

        try {
            $this->queue()->upload(new \SplFileInfo($this->zip(['readme.txt' => 'Not an export'])), 'cms-export.zip');
            self::fail('A zip without a manifest is refused');
        } catch (InvalidExportException $e) {
            self::assertSame('cms-export.zip has no manifest.json, so it isn\'t an export', $e->getMessage());
        }

        self::assertSame([], $this->dispatched);
    }

    /**
     * @test
     */
    public function it_refuses_a_file_that_is_not_a_zip(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'setono_sylius_cms_test_');
        file_put_contents($file, 'not a zip');
        $this->files[] = $file;

        $this->expectException(InvalidExportException::class);

        $this->queue()->upload(new \SplFileInfo($file), 'cms-export.zip');
    }

    /**
     * @test
     */
    public function it_has_the_worker_import_what_an_admin_user_confirms(): void
    {
        $import = self::checked();
        $this->manager->flush()->shouldBeCalledOnce();

        $this->queue()->confirm($import, true, false);

        self::assertSame(ImportState::Importing, $import->getState());
        self::assertTrue($import->isOverwrite());
        self::assertFalse($import->isPublish());
        self::assertEquals([new RunImport($import->getUuid())], array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $this->dispatched));
    }

    /**
     * @test
     */
    public function it_abandons_an_import_and_deletes_its_zip(): void
    {
        $import = self::checked();
        $this->manager->flush()->shouldBeCalledOnce();
        $this->storage->delete('imports/abc.zip')->shouldBeCalledOnce();

        $this->queue()->abandon($import);

        self::assertSame(ImportState::Abandoned, $import->getState());
        self::assertSame('2026-11-20T08:00:00+00:00', $import->getFinishedAt()?->format(\DATE_ATOM));
    }

    private function queue(): ImportQueue
    {
        $factory = $this->prophesize(FactoryInterface::class);
        $factory->createNew()->willReturn(new Import());

        $bus = $this->prophesize(MessageBusInterface::class);
        $dispatched = &$this->dispatched;
        $bus->dispatch(Argument::any(), Argument::any())->will(static function (array $arguments) use (&$dispatched): Envelope {
            $stamps = $arguments[1] ?? [];
            \assert(is_object($arguments[0]) && is_array($stamps));
            $envelope = Envelope::wrap($arguments[0], $stamps);
            $dispatched[] = $envelope;

            return $envelope;
        });

        return new ImportQueue(
            new UploadedExports($this->storage->reveal(), new NullLogger()),
            $factory->reveal(),
            $this->manager->reveal(),
            $bus->reveal(),
            new MockClock('2026-11-20 08:00:00', 'UTC'),
        );
    }

    private static function checked(): ImportInterface
    {
        $import = new Import();
        $import->setFilename('cms-export.zip');
        $import->setPath('imports/abc.zip');
        $import->setCreatedAt(new \DateTimeImmutable('2026-11-20 07:00:00'));
        $import->check([]);

        return $import;
    }

    /**
     * @param array<string, string> $files
     */
    private function zip(array $files): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'setono_sylius_cms_test_');
        $this->files[] = $file;
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::OVERWRITE));
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $file;
    }
}
