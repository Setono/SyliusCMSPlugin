<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
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

    /** @var ObjectProphecy<WorkingCopyManagerInterface> */
    private ObjectProphecy $workingCopies;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    /** @var ObjectProphecy<EntryValidatorInterface> */
    private ObjectProphecy $entryValidator;

    private EventDispatcher $eventDispatcher;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->workingCopies = $this->prophesize(WorkingCopyManagerInterface::class);
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($this->manager->reveal());
        $this->entryValidator = $this->prophesize(EntryValidatorInterface::class);
        $this->entryValidator->validate(Argument::any())->willReturn([]);

        $calls = &$this->calls;
        $this->manager->wrapInTransaction(Argument::type('callable'))->will(static function (array $arguments) use (&$calls): mixed {
            $transaction = $arguments[0] ?? null;
            self::assertIsCallable($transaction);
            $calls[] = 'begin';
            $result = $transaction();
            $calls[] = 'commit';

            return $result;
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
    public function it_publishes_the_working_copy_in_a_transaction_and_dispatches_events_around_it(): void
    {
        $page = self::page();
        $workingCopy = clone $page;
        $this->workingCopies->getWorkingCopy($page)->willReturn($workingCopy);
        $calls = &$this->calls;
        $this->workingCopies->applyDraft($page)->will(static function () use ($page, &$calls): void {
            $calls[] = 'apply draft';
            self::assertFalse($page->isPublished());
        });

        $this->publisher()->publish($page);

        self::assertSame([BeforeEntryPublish::class, 'begin', 'apply draft', 'commit', EntryPublished::class], $this->calls);

        $before = $this->events[0];
        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertSame($workingCopy, $before->entry);
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
    public function a_republish_reports_the_slugs_shoppers_saw_before_and_see_now(): void
    {
        $page = self::page();
        $page->getTranslation('da_DK')->setTitle('Om os');
        $page->publish(new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));
        $this->workingCopies->getWorkingCopy($page)->willReturn(clone $page);
        $this->workingCopies->applyDraft($page)->will(static function () use ($page): void {
            $page->setSlug('about');
            $page->getTranslation('da_DK')->setSlug('om-os');
        });

        $this->publisher()->publish($page);

        $published = $this->events[1];
        self::assertInstanceOf(EntryPublished::class, $published);
        self::assertSame(['en_US' => 'about-us'], $published->oldSlugs);
        self::assertSame(['en_US' => 'about', 'da_DK' => 'om-os'], $published->newSlugs);
        self::assertSame('2026-11-14T11:30:00+00:00', $page->getChangedAt()->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function a_veto_stops_the_publish_with_its_message(): void
    {
        $page = self::page();
        $this->workingCopies->getWorkingCopy($page)->willReturn($page);
        $this->workingCopies->applyDraft(Argument::any())->shouldNotBeCalled();
        $this->manager->wrapInTransaction(Argument::any())->shouldNotBeCalled();
        $this->eventDispatcher->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('Not on a Sunday'));

        try {
            $this->publisher()->publish($page);
            self::fail('The publish was not vetoed');
        } catch (PublishVetoedException $e) {
            self::assertSame('Not on a Sunday', $e->getMessage());
        }

        self::assertFalse($page->isPublished());
        self::assertSame([BeforeEntryPublish::class], $this->calls);
    }

    /**
     * @test
     */
    public function it_refuses_a_working_copy_with_errors_before_listeners_hear_of_it(): void
    {
        $page = self::page();
        $workingCopy = clone $page;
        $errors = [new ValidationError('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'text', 'en_US', 'This field is required.')];
        $this->workingCopies->getWorkingCopy($page)->willReturn($workingCopy);
        $this->entryValidator->validate($workingCopy)->willReturn($errors);
        $this->workingCopies->applyDraft(Argument::any())->shouldNotBeCalled();
        $this->manager->wrapInTransaction(Argument::any())->shouldNotBeCalled();

        try {
            $this->publisher()->publish($page);
            self::fail('The publish was not refused');
        } catch (InvalidEntryException $e) {
            self::assertSame($errors, $e->errors);
            self::assertSame('Validation found errors in the entry, so it can\'t be published', $e->getMessage());
        }

        self::assertFalse($page->isPublished());
        self::assertSame([], $this->calls);
    }

    /**
     * @test
     *
     * @dataProvider managersWithoutTransactions
     */
    public function it_needs_a_doctrine_entity_manager_for_the_entry(?ObjectManager $manager): void
    {
        $page = self::page();
        $this->workingCopies->getWorkingCopy($page)->willReturn($page);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($manager);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Page::class);

        $this->publisher()->publish($page);
    }

    /**
     * @return iterable<string, array{ObjectManager|null}>
     */
    public function managersWithoutTransactions(): iterable
    {
        yield 'no manager' => [null];
        yield 'an object manager' => [$this->prophesize(ObjectManager::class)->reveal()];
    }

    private function publisher(): EntryPublisher
    {
        $clock = $this->prophesize(ClockInterface::class);
        $clock->now()->willReturn(new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        return new EntryPublisher(
            $this->workingCopies->reveal(),
            $this->managerRegistry->reveal(),
            $clock->reveal(),
            $this->eventDispatcher,
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            ]),
            $this->entryValidator->reveal(),
        );
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
