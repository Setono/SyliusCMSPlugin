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
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Publishing\EntryLock;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisher;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishCheck;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionRecorderInterface;
use Setono\SyliusCMSPlugin\Routing\OldSlugRecorderInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntryPublisherTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ScheduledPublishRepositoryInterface> */
    private ObjectProphecy $scheduledPublishes;

    /** @var ObjectProphecy<WorkingCopyManagerInterface> */
    private ObjectProphecy $workingCopies;

    /** @var ObjectProphecy<EntryValidatorInterface> */
    private ObjectProphecy $entryValidator;

    /** @var ObjectProphecy<RevisionRecorderInterface> */
    private ObjectProphecy $revisions;

    /** @var ObjectProphecy<OldSlugRecorderInterface> */
    private ObjectProphecy $oldSlugs;

    private EventDispatcher $eventDispatcher;

    private Page $page;

    private Page $workingCopy;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->page = self::page();
        $this->workingCopy = clone $this->page;

        $this->manager = $manager = $this->prophesize(EntityManagerInterface::class);
        $connection = $this->prophesize(Connection::class);
        $manager->getConnection()->willReturn($connection->reveal());
        $connection->beginTransaction()->will($this->record('begin'));
        $connection->commit()->will($this->record('commit'));
        $connection->rollBack()->will($this->record('roll back'));
        $manager->lock($this->page, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $manager->refresh($this->page)->will($this->record('read again'));
        $manager->flush()->will($this->record('flush'));
        $manager->remove(Argument::type(ScheduledPublish::class))->will($this->record('remove the scheduled publish'));
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($manager->reveal());

        $calls = &$this->calls;
        $workingCopy = $this->workingCopy;
        $this->workingCopies = $this->prophesize(WorkingCopyManagerInterface::class);
        $this->workingCopies->getWorkingCopy($this->page)->will(static function () use (&$calls, $workingCopy): EntryInterface {
            $calls[] = 'get working copy';

            return $workingCopy;
        });

        $this->entryValidator = $this->prophesize(EntryValidatorInterface::class);
        $this->entryValidator->validate($this->workingCopy)->will(static function () use (&$calls): array {
            $calls[] = 'validate';

            return [];
        });

        // The page has no scheduled publish, unless a test schedules one
        $this->scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn(null);

        $this->revisions = $this->prophesize(RevisionRecorderInterface::class);
        $this->revisions->record(Argument::cetera())->will($this->record('store a revision'));

        $this->oldSlugs = $this->prophesize(OldSlugRecorderInterface::class);
        $this->oldSlugs->record(Argument::cetera())->will($this->record('record old slugs'));

        $this->eventDispatcher = new EventDispatcher();
        foreach ([BeforeEntryPublish::class, EntryPublished::class] as $event) {
            $this->eventDispatcher->addListener($event, function (object $event): void {
                $this->events[] = $event;
                $this->calls[] = $event::class;
            });
        }
    }

    /**
     * @test
     */
    public function it_validates_asks_listeners_about_and_publishes_one_working_copy_read_with_the_entry_locked(): void
    {
        $page = $this->page;
        $calls = &$this->calls;
        $this->workingCopies->applyToRows($page, $this->workingCopy)->will(static function () use ($page, &$calls): void {
            $calls[] = 'apply to rows';
            self::assertFalse($page->isPublished());
        });

        $this->publisher()->publish($page);

        self::assertSame(
            ['begin', 'lock to write', 'read again', 'get working copy', 'validate', BeforeEntryPublish::class, 'apply to rows', 'record old slugs', 'store a revision', 'flush', 'commit', EntryPublished::class],
            $this->calls,
        );

        // The revision holds what the rows hold now, published by whoever is signed in
        $this->revisions->record($page, new \DateTimeImmutable('2026-11-14 11:30:00', new \DateTimeZone('UTC')), false, null)->shouldHaveBeenCalledOnce();
        // A first publish changes no slug shoppers saw
        $this->oldSlugs->record($page, [], ['en_US' => 'about-us'])->shouldHaveBeenCalledOnce();

        $before = $this->events[0];
        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertSame($this->workingCopy, $before->entry);
        self::assertFalse($before->scheduled);

        $published = $this->events[1];
        self::assertInstanceOf(EntryPublished::class, $published);
        self::assertSame($page->getUuid(), $published->uuid);
        self::assertSame('page', $published->contentType);
        self::assertSame([], $published->oldSlugs);
        self::assertSame(['en_US' => 'about-us'], $published->newSlugs);
        self::assertFalse($published->scheduled);

        self::assertTrue($page->isPublished());
        self::assertSame('2026-11-14T11:30:00+00:00', $page->getLastPublishedAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-14T11:30:00+00:00', $page->getChangedAt()->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_reports_the_slugs_shoppers_saw_before_and_see_now_on_a_republish(): void
    {
        $page = $this->page;
        $page->getTranslation('da_DK')->setTitle('Om os');
        $page->getTranslation('da_DK')->setSlug('om');
        $page->publish(new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));
        $this->workingCopies->applyToRows($page, $this->workingCopy)->will(static function () use ($page): void {
            $page->setSlug('about');
            $page->getTranslation('da_DK')->setSlug('om-os');
        });

        $this->publisher()->publish($page);

        $published = $this->events[1];
        self::assertInstanceOf(EntryPublished::class, $published);
        self::assertSame(['en_US' => 'about-us', 'da_DK' => 'om'], $published->oldSlugs);
        self::assertSame(['en_US' => 'about', 'da_DK' => 'om-os'], $published->newSlugs);
        $this->oldSlugs->record($page, ['en_US' => 'about-us', 'da_DK' => 'om'], ['en_US' => 'about', 'da_DK' => 'om-os'])->shouldHaveBeenCalledOnce();
        self::assertSame('2026-11-14T11:30:00+00:00', $page->getChangedAt()->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_stops_the_publish_with_the_message_of_a_veto(): void
    {
        $this->workingCopies->applyToRows(Argument::cetera())->shouldNotBeCalled();
        $this->eventDispatcher->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('Not on a Sunday'));

        try {
            $this->publisher()->publish($this->page);
            self::fail('The publish was not vetoed');
        } catch (PublishVetoedException $e) {
            self::assertSame('Not on a Sunday', $e->getMessage());
        }

        self::assertFalse($this->page->isPublished());
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'validate', BeforeEntryPublish::class, 'roll back'], $this->calls);
        $this->revisions->record(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function it_refuses_a_working_copy_with_errors_before_listeners_hear_of_it(): void
    {
        $errors = [new ValidationError('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'text', 'en_US', 'This field is required.')];
        $this->entryValidator->validate($this->workingCopy)->willReturn($errors);
        $this->workingCopies->applyToRows(Argument::cetera())->shouldNotBeCalled();

        try {
            $this->publisher()->publish($this->page);
            self::fail('The publish was not refused');
        } catch (InvalidEntryException $e) {
            self::assertSame($errors, $e->errors);
            self::assertSame('Validation found errors in the entry, so it can\'t be published', $e->getMessage());
        }

        self::assertFalse($this->page->isPublished());
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'roll back'], $this->calls);
        $this->revisions->record(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function it_ends_the_entrys_scheduled_publish_on_a_publish_now(): void
    {
        $scheduledPublish = $this->scheduledPublish();
        $this->workingCopies->applyToRows($this->page, $this->workingCopy)->will($this->record('apply to rows'));

        $this->publisher()->publish($this->page);

        self::assertSame(
            ['begin', 'lock to write', 'read again', 'get working copy', 'validate', BeforeEntryPublish::class, 'remove the scheduled publish', 'apply to rows', 'record old slugs', 'store a revision', 'flush', 'commit', EntryPublished::class],
            $this->calls,
        );
        $this->manager->remove($scheduledPublish)->shouldHaveBeenCalledOnce();
        // Published now, by whoever is signed in
        $this->revisions->record($this->page, new \DateTimeImmutable('2026-11-14 11:30:00', new \DateTimeZone('UTC')), false, null)->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_runs_a_scheduled_publish_as_a_publish_recorded_as_scheduled_by_who_scheduled_it(): void
    {
        $scheduledPublish = $this->scheduledPublish();
        $this->workingCopies->applyToRows($this->page, $this->workingCopy)->will($this->record('apply to rows'));

        self::assertTrue($this->publisher()->publishScheduled($this->page, $scheduledPublish->getToken()));

        self::assertSame(
            ['begin', 'lock to write', 'read again', 'get working copy', 'validate', BeforeEntryPublish::class, 'remove the scheduled publish', 'apply to rows', 'record old slugs', 'store a revision', 'flush', 'commit', EntryPublished::class],
            $this->calls,
        );
        $this->manager->remove($scheduledPublish)->shouldHaveBeenCalledOnce();
        $this->revisions->record($this->page, new \DateTimeImmutable('2026-11-14 11:30:00', new \DateTimeZone('UTC')), true, 'Jane Roe')->shouldHaveBeenCalledOnce();

        $before = $this->events[0];
        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertSame($this->workingCopy, $before->entry);
        self::assertTrue($before->scheduled);

        $published = $this->events[1];
        self::assertInstanceOf(EntryPublished::class, $published);
        self::assertSame($this->page->getUuid(), $published->uuid);
        self::assertSame(['en_US' => 'about-us'], $published->newSlugs);
        self::assertTrue($published->scheduled);

        self::assertTrue($this->page->isPublished());
    }

    /**
     * @test
     *
     * @dataProvider provideStaleMessages
     */
    public function it_does_nothing_for_a_message_whose_scheduled_publish_was_cancelled_replaced_or_stopped(?string $token, ?string $failure): void
    {
        $scheduledPublish = $this->scheduledPublish();
        if (null !== $failure) {
            $scheduledPublish->fail($failure);
        }
        $this->workingCopies->applyToRows(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->publisher()->publishScheduled($this->page, $token ?? $scheduledPublish->getToken()));

        self::assertSame(['begin', 'lock to write', 'read again', 'flush', 'commit'], $this->calls);
        self::assertSame([], $this->events);
        self::assertFalse($this->page->isPublished());
        $this->revisions->record(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function provideStaleMessages(): iterable
    {
        yield 'replaced, so the message carries the token of the one before' => ['5f0c8c0e-6b1a-4c2e-9d3f-7a8b9c0d1e2f', null];
        yield 'stopped when an earlier message of it ran' => [null, 'Not on a Sunday'];
    }

    /**
     * @test
     */
    public function it_does_nothing_for_a_message_for_an_entry_without_a_scheduled_publish(): void
    {
        $this->workingCopies->applyToRows(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->publisher()->publishScheduled($this->page, '5f0c8c0e-6b1a-4c2e-9d3f-7a8b9c0d1e2f'));

        self::assertSame(['begin', 'lock to write', 'read again', 'flush', 'commit'], $this->calls);
        self::assertSame([], $this->events);
    }

    /**
     * @test
     */
    public function it_stops_a_scheduled_publish_on_a_veto_and_leaves_it_for_the_caller_to_flag(): void
    {
        $scheduledPublish = $this->scheduledPublish();
        $this->workingCopies->applyToRows(Argument::cetera())->shouldNotBeCalled();
        $this->eventDispatcher->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('Not on a Sunday'));

        try {
            $this->publisher()->publishScheduled($this->page, $scheduledPublish->getToken());
            self::fail('The scheduled publish was not vetoed');
        } catch (PublishVetoedException $e) {
            self::assertSame('Not on a Sunday', $e->getMessage());
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'validate', BeforeEntryPublish::class, 'roll back'], $this->calls);
        $this->manager->remove(Argument::any())->shouldNotHaveBeenCalled();
        self::assertFalse($this->page->isPublished());
    }

    /**
     * @test
     */
    public function it_stops_a_scheduled_publish_with_errors_before_listeners_hear_of_it(): void
    {
        $scheduledPublish = $this->scheduledPublish();
        $errors = [new ValidationError(null, 'slug', 'en_US', 'This slug is taken.')];
        $this->entryValidator->validate($this->workingCopy)->willReturn($errors);

        try {
            $this->publisher()->publishScheduled($this->page, $scheduledPublish->getToken());
            self::fail('The scheduled publish was not refused');
        } catch (InvalidEntryException $e) {
            self::assertSame($errors, $e->errors);
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'roll back'], $this->calls);
        $this->manager->remove(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * A publish of the page scheduled by Jane Roe, which waits
     */
    private function scheduledPublish(): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($this->page);
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-14 11:00:00', new \DateTimeZone('UTC')), 'Jane Roe');
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn($scheduledPublish);

        return $scheduledPublish;
    }

    private function publisher(): EntryPublisher
    {
        $clock = $this->prophesize(ClockInterface::class);
        $clock->now()->willReturn(new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        return new EntryPublisher(
            $this->workingCopies->reveal(),
            new EntryLock($this->managerRegistry->reveal()),
            $clock->reveal(),
            $this->eventDispatcher,
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            ]),
            new PublishCheck($this->entryValidator->reveal(), $this->eventDispatcher),
            $this->revisions->reveal(),
            $this->oldSlugs->reveal(),
            $this->scheduledPublishes->reveal(),
            $this->manager->reveal(),
        );
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

    private static function page(): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');

        return $page;
    }
}
