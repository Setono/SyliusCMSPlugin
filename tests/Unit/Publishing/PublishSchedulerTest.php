<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryPublishCancelled;
use Setono\SyliusCMSPlugin\Event\EntryPublishFailed;
use Setono\SyliusCMSPlugin\Event\EntryPublishScheduled;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryLock;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishCheck;
use Setono\SyliusCMSPlugin\Publishing\PublishScheduler;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDue;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishInThePastException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PublishSchedulerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<EntryValidatorInterface> */
    private ObjectProphecy $entryValidator;

    /** @var ObjectProphecy<ScheduledPublishRepositoryInterface> */
    private ObjectProphecy $scheduledPublishes;

    /** @var ObjectProphecy<FactoryInterface<ScheduledPublishInterface>> */
    private ObjectProphecy $scheduledPublishFactory;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;

    private EventDispatcher $eventDispatcher;

    private TokenStorage $tokenStorage;

    private Page $page;

    private Page $workingCopy;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    /** @var list<object> */
    private array $events = [];

    /** @var list<Envelope> */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->page = new Page();
        $this->workingCopy = clone $this->page;

        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $connection = $this->prophesize(Connection::class);
        $this->manager->getConnection()->willReturn($connection->reveal());
        $connection->beginTransaction()->will($this->record('begin'));
        $connection->commit()->will($this->record('commit'));
        $connection->rollBack()->will($this->record('roll back'));
        $this->manager->lock($this->page, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $this->manager->refresh($this->page)->will($this->record('read again'));
        $this->manager->flush()->will($this->record('flush'));
        $this->manager->persist(Argument::type(ScheduledPublish::class))->will($this->record('persist the scheduled publish'));
        $this->manager->remove(Argument::type(ScheduledPublish::class))->will($this->record('remove the scheduled publish'));

        $calls = &$this->calls;
        $this->entryValidator = $this->prophesize(EntryValidatorInterface::class);
        $this->entryValidator->validate($this->workingCopy)->will(static function () use (&$calls): array {
            $calls[] = 'validate';

            return [];
        });

        $this->scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn(null);
        $this->scheduledPublishFactory = $this->prophesize(FactoryInterface::class);
        $this->scheduledPublishFactory->createNew()->willReturn(new ScheduledPublish());

        $sent = &$this->sent;
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus->dispatch(Argument::cetera())->will(static function (array $arguments) use (&$calls, &$sent): Envelope {
            $calls[] = 'send the message';
            $envelope = Envelope::wrap($arguments[0], $arguments[1] ?? []);
            $sent[] = $envelope;

            return $envelope;
        });

        $this->eventDispatcher = new EventDispatcher();
        foreach ([BeforeEntryPublish::class, EntryPublishScheduled::class, EntryPublishCancelled::class, EntryPublishFailed::class] as $event) {
            $this->eventDispatcher->addListener($event, function (object $event): void {
                $this->events[] = $event;
                $this->calls[] = $event::class;
            });
        }

        $admin = new AdminUser();
        $admin->setUsername('jane');
        $admin->setFirstName('Jane');
        $admin->setLastName('Roe');
        $this->tokenStorage = new TokenStorage();
        $this->tokenStorage->setToken(new UsernamePasswordToken($admin, 'admin', []));
    }

    /**
     * @test
     */
    public function it_checks_the_working_copy_then_schedules_its_publish_and_sends_the_message_delayed_until_then(): void
    {
        $this->scheduler()->schedule($this->page, new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame(
            ['begin', 'lock to write', 'read again', 'validate', BeforeEntryPublish::class, 'persist the scheduled publish', 'send the message', 'flush', 'commit', EntryPublishScheduled::class],
            $this->calls,
        );

        $before = $this->events[0];
        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertSame($this->workingCopy, $before->entry);
        self::assertTrue($before->scheduled);

        $scheduledPublish = $this->persisted();
        self::assertSame($this->page, $scheduledPublish->getEntry());
        self::assertSame('2026-11-14T11:30:00+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));
        self::assertSame('Jane Roe', $scheduledPublish->getScheduledBy());

        // An hour and a half from now
        self::assertCount(1, $this->sent);
        $message = $this->sent[0]->getMessage();
        self::assertInstanceOf(ScheduledPublishDue::class, $message);
        self::assertSame($this->page->getUuid(), $message->entryUuid);
        self::assertSame($scheduledPublish->getToken(), $message->token);
        self::assertSame(5_400_000, $this->sent[0]->last(DelayStamp::class)?->getDelay());

        $scheduled = $this->events[1];
        self::assertInstanceOf(EntryPublishScheduled::class, $scheduled);
        self::assertSame($this->page->getUuid(), $scheduled->uuid);
        self::assertSame('2026-11-14T11:30:00+00:00', $scheduled->at->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_replaces_the_scheduled_publish_the_entry_has_with_a_new_token(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($this->page);
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00'), 'John Doe');
        $scheduledPublish->fail('Not on a Sunday');
        $replaced = $scheduledPublish->getToken();
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn($scheduledPublish);

        $this->scheduler()->schedule($this->page, new \DateTimeImmutable('2026-11-14 10:00:01', new \DateTimeZone('UTC')));

        self::assertSame(['begin', 'lock to write', 'read again', 'validate', BeforeEntryPublish::class, 'send the message', 'flush', 'commit', EntryPublishScheduled::class], $this->calls);
        self::assertSame('2026-11-14T10:00:01+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));
        self::assertSame('Jane Roe', $scheduledPublish->getScheduledBy());
        self::assertFalse($scheduledPublish->isFailed());
        self::assertNotSame($replaced, $scheduledPublish->getToken());

        $message = $this->sent[0]->getMessage();
        self::assertInstanceOf(ScheduledPublishDue::class, $message);
        self::assertSame($scheduledPublish->getToken(), $message->token);
        self::assertSame(1000, $this->sent[0]->last(DelayStamp::class)?->getDelay());
    }

    /**
     * @test
     */
    public function it_records_no_one_as_who_scheduled_it_when_no_one_is_signed_in(): void
    {
        $this->tokenStorage->setToken(null);

        $this->scheduler()->schedule($this->page, new \DateTimeImmutable('2026-11-14 12:00:00', new \DateTimeZone('UTC')));

        self::assertNull($this->persisted()->getScheduledBy());
    }

    /**
     * @test
     *
     * @dataProvider provideTimesNotInTheFuture
     */
    public function it_refuses_a_time_that_is_not_in_the_future(string $at): void
    {
        try {
            $this->scheduler()->schedule($this->page, new \DateTimeImmutable($at, new \DateTimeZone('UTC')));
            self::fail('The time was not refused');
        } catch (ScheduledPublishInThePastException $e) {
            self::assertSame($at, $e->at->format('Y-m-d H:i:s'));
        }

        self::assertSame([], $this->calls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTimesNotInTheFuture(): iterable
    {
        yield 'now' => ['2026-11-14 10:00:00'];
        yield 'a minute ago' => ['2026-11-14 09:59:00'];
    }

    /**
     * @test
     */
    public function it_schedules_nothing_when_validation_finds_errors(): void
    {
        $errors = [new ValidationError(null, 'title', 'en_US', 'This value should not be blank.')];
        $this->entryValidator->validate($this->workingCopy)->willReturn($errors);

        try {
            $this->scheduler()->schedule($this->page, new \DateTimeImmutable('2026-11-14 12:00:00', new \DateTimeZone('UTC')));
            self::fail('The schedule was not refused');
        } catch (InvalidEntryException $e) {
            self::assertSame($errors, $e->errors);
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
        self::assertSame([], $this->sent);
    }

    /**
     * @test
     */
    public function it_schedules_nothing_when_a_listener_vetoes(): void
    {
        $this->eventDispatcher->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('Not on a Sunday'));

        try {
            $this->scheduler()->schedule($this->page, new \DateTimeImmutable('2026-11-14 12:00:00', new \DateTimeZone('UTC')));
            self::fail('The schedule was not vetoed');
        } catch (PublishVetoedException $e) {
            self::assertSame('Not on a Sunday', $e->getMessage());
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'validate', BeforeEntryPublish::class, 'roll back'], $this->calls);
        self::assertSame([], $this->sent);
    }

    /**
     * @test
     */
    public function it_cancels_the_scheduled_publish_and_tells_listeners(): void
    {
        $scheduledPublish = $this->scheduledPublish();

        $this->scheduler()->cancel($this->page);

        self::assertSame(['begin', 'lock to write', 'read again', 'remove the scheduled publish', 'flush', 'commit', EntryPublishCancelled::class], $this->calls);
        $this->manager->remove($scheduledPublish)->shouldHaveBeenCalledOnce();
        $cancelled = $this->events[0];
        self::assertInstanceOf(EntryPublishCancelled::class, $cancelled);
        self::assertSame($this->page->getUuid(), $cancelled->uuid);
    }

    /**
     * @test
     */
    public function it_clears_a_failed_scheduled_publish_without_telling_listeners(): void
    {
        $this->scheduledPublish()->fail('Not on a Sunday');

        $this->scheduler()->cancel($this->page);

        self::assertSame(['begin', 'lock to write', 'read again', 'remove the scheduled publish', 'flush', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_cancels_nothing_for_an_entry_without_a_scheduled_publish(): void
    {
        $this->scheduler()->cancel($this->page);

        self::assertSame(['begin', 'lock to write', 'read again', 'flush', 'commit'], $this->calls);
        self::assertSame([], $this->events);
    }

    /**
     * @test
     */
    public function it_flags_the_scheduled_publish_that_was_stopped_as_failed_and_tells_listeners_why(): void
    {
        $scheduledPublish = $this->scheduledPublish();

        $this->scheduler()->fail($this->page, $scheduledPublish->getToken(), 'Not on a Sunday');

        self::assertSame(['begin', 'lock to write', 'read again', 'flush', 'commit', EntryPublishFailed::class], $this->calls);
        self::assertTrue($scheduledPublish->isFailed());
        self::assertSame('Not on a Sunday', $scheduledPublish->getFailureReason());

        $failed = $this->events[0];
        self::assertInstanceOf(EntryPublishFailed::class, $failed);
        self::assertSame($this->page->getUuid(), $failed->uuid);
        self::assertSame('Not on a Sunday', $failed->reason);
    }

    /**
     * @test
     */
    public function it_flags_nothing_when_the_scheduled_publish_was_replaced_since(): void
    {
        $scheduledPublish = $this->scheduledPublish();

        $this->scheduler()->fail($this->page, '5f0c8c0e-6b1a-4c2e-9d3f-7a8b9c0d1e2f', 'Not on a Sunday');

        self::assertFalse($scheduledPublish->isFailed());
        self::assertSame(['begin', 'lock to write', 'read again', 'flush', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_keeps_the_reason_a_scheduled_publish_failed_first(): void
    {
        $scheduledPublish = $this->scheduledPublish();
        $scheduledPublish->fail('Not on a Sunday');

        $this->scheduler()->fail($this->page, $scheduledPublish->getToken(), 'Not on a Monday');

        self::assertSame('Not on a Sunday', $scheduledPublish->getFailureReason());
        self::assertSame([], $this->events);
    }

    /**
     * @test
     */
    public function it_flags_nothing_when_the_scheduled_publish_was_cancelled_since(): void
    {
        $this->scheduler()->fail($this->page, '5f0c8c0e-6b1a-4c2e-9d3f-7a8b9c0d1e2f', 'Not on a Sunday');

        self::assertSame(['begin', 'lock to write', 'read again', 'flush', 'commit'], $this->calls);
        self::assertSame([], $this->events);
    }

    private function scheduler(): PublishScheduler
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Page::class)->willReturn($this->manager->reveal());

        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($this->page)->willReturn($this->workingCopy);

        return new PublishScheduler(
            new EntryLock($managerRegistry->reveal()),
            $workingCopies->reveal(),
            new PublishCheck($this->entryValidator->reveal(), $this->eventDispatcher),
            $this->scheduledPublishes->reveal(),
            $this->scheduledPublishFactory->reveal(),
            $this->manager->reveal(),
            $this->messageBus->reveal(),
            $this->eventDispatcher,
            new MockClock('2026-11-14 10:00:00', 'UTC'),
            $this->tokenStorage,
        );
    }

    /**
     * A publish of the page scheduled by John Doe, which waits
     */
    private function scheduledPublish(): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($this->page);
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC')), 'John Doe');
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn($scheduledPublish);

        return $scheduledPublish;
    }

    /**
     * The scheduled publish the scheduler created and persisted
     */
    private function persisted(): ScheduledPublishInterface
    {
        $created = $this->scheduledPublishFactory->reveal()->createNew();
        $this->manager->persist($created)->shouldHaveBeenCalledOnce();

        return $created;
    }

    /**
     * Prophecy binds a promise's closure to the double, so it reaches the calls by reference
     */
    private function record(string $call): \Closure
    {
        $calls = &$this->calls;

        return static function () use ($call, &$calls): void {
            $calls[] = $call;
        };
    }
}
