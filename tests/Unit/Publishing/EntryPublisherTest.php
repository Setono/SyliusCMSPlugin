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
use Setono\SyliusCMSPlugin\Publishing\EntryLock;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisher;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntryPublisherTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    /** @var ObjectProphecy<WorkingCopyManagerInterface> */
    private ObjectProphecy $workingCopies;

    /** @var ObjectProphecy<EntryValidatorInterface> */
    private ObjectProphecy $entryValidator;

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

        $manager = $this->prophesize(EntityManagerInterface::class);
        $connection = $this->prophesize(Connection::class);
        $manager->getConnection()->willReturn($connection->reveal());
        $connection->beginTransaction()->will($this->record('begin'));
        $connection->commit()->will($this->record('commit'));
        $connection->rollBack()->will($this->record('roll back'));
        $manager->lock($this->page, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $manager->refresh($this->page)->will($this->record('read again'));
        $manager->flush()->will($this->record('flush'));
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
            ['begin', 'lock to write', 'read again', 'get working copy', 'validate', BeforeEntryPublish::class, 'apply to rows', 'flush', 'commit', EntryPublished::class],
            $this->calls,
        );

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
            $this->entryValidator->reveal(),
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
