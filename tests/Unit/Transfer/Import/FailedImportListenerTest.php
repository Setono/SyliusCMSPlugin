<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Import;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\AbandonImport;
use Setono\SyliusCMSPlugin\Transfer\Import\FailedImportListener;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportQueueInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\RunImport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * See "Who runs it" in docs/spec/transfer.md
 */
final class FailedImportListenerTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-0000-7000-8000-000000000001';

    /** @var ObjectProphecy<ImportRepositoryInterface> */
    private ObjectProphecy $imports;

    /** @var ObjectProphecy<ImportQueueInterface> */
    private ObjectProphecy $queue;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $registry;

    protected function setUp(): void
    {
        $this->imports = $this->prophesize(ImportRepositoryInterface::class);
        $this->queue = $this->prophesize(ImportQueueInterface::class);
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->manager->isOpen()->willReturn(true);
        $this->registry = $this->prophesize(ManagerRegistry::class);
    }

    /**
     * @test
     */
    public function it_fails_an_import_messenger_gave_up_on_with_the_error_its_handler_threw(): void
    {
        $import = self::importing();
        $this->imports->findOneByUuid(self::UUID)->willReturn($import);
        $this->queue->fail($import, 'The database went away')->shouldBeCalledOnce();

        $this->listener()(self::failed(new RunImport(self::UUID), new \RuntimeException('The database went away')));
    }

    /**
     * @test
     */
    public function it_leaves_an_import_messenger_retries_alone(): void
    {
        $this->imports->findOneByUuid(Argument::any())->shouldNotBeCalled();
        $this->queue->fail(Argument::cetera())->shouldNotBeCalled();

        $event = self::failed(new RunImport(self::UUID), new \RuntimeException('The database went away'));
        $event->setForRetry();

        $this->listener()($event);
    }

    /**
     * @test
     */
    public function it_leaves_an_import_alone_once_it_no_longer_waits_for_the_worker(): void
    {
        $import = self::importing();
        $import->finish([], new \DateTimeImmutable());
        $this->imports->findOneByUuid(self::UUID)->willReturn($import);
        $this->queue->fail(Argument::cetera())->shouldNotBeCalled();

        $this->listener()(self::failed(new RunImport(self::UUID), new \RuntimeException('Late')));
    }

    /**
     * @test
     */
    public function it_leaves_other_messages_alone(): void
    {
        $this->imports->findOneByUuid(Argument::any())->shouldNotBeCalled();

        $this->listener()(self::failed(new AbandonImport(self::UUID), new \RuntimeException('The database went away')));
    }

    /**
     * The failed run may have closed the entity manager, as Doctrine does after a failed flush
     *
     * @test
     */
    public function it_reopens_the_entity_manager_the_failed_run_closed_before_it_records_the_failure(): void
    {
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->manager->isOpen()->willReturn(false);
        $manager = $this->manager->reveal();
        $this->registry->getManagers()->willReturn(['default' => $manager]);
        $this->registry->resetManager('default')->willReturn($manager)->shouldBeCalledOnce();
        $import = self::importing();
        $this->imports->findOneByUuid(self::UUID)->willReturn($import);
        $this->queue->fail($import, 'The database went away')->shouldBeCalledOnce();

        $this->listener()(self::failed(new RunImport(self::UUID), new \RuntimeException('The database went away')));
    }

    private function listener(): FailedImportListener
    {
        return new FailedImportListener($this->imports->reveal(), $this->queue->reveal(), $this->manager->reveal(), $this->registry->reveal());
    }

    /**
     * As the worker reports it: the handler's error wrapped in Messenger's
     */
    private static function failed(object $message, \Throwable $error): WorkerMessageFailedEvent
    {
        $envelope = new Envelope($message);

        return new WorkerMessageFailedEvent($envelope, 'main', new HandlerFailedException($envelope, [$error]));
    }

    private static function importing(): ImportInterface
    {
        $import = new Import();
        $import->setFilename('cms-export.zip');
        $import->setPath('imports/abc.zip');
        $import->setCreatedAt(new \DateTimeImmutable('2026-11-20 07:00:00'));
        $import->check([]);
        $import->confirm(false, false);

        return $import;
    }
}
