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
use Setono\SyliusCMSPlugin\Event\BeforeEntryDelete;
use Setono\SyliusCMSPlugin\Event\EntryDeleted;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Publishing\DeleteVetoedException;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleter;
use Setono\SyliusCMSPlugin\Publishing\EntryLock;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntryDeleterTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    private EventDispatcher $eventDispatcher;

    private Page $page;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->page = new Page();

        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $connection = $this->prophesize(Connection::class);
        $this->manager->getConnection()->willReturn($connection->reveal());
        $connection->beginTransaction()->will($this->record('begin'));
        $connection->commit()->will($this->record('commit'));
        $connection->rollBack()->will($this->record('roll back'));
        $this->manager->lock($this->page, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $this->manager->refresh($this->page)->will($this->record('read again'));
        $this->manager->remove($this->page)->will($this->record('remove'));
        $this->manager->flush()->will($this->record('flush'));
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($this->manager->reveal());

        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        $this->placements->findBySnippet(Argument::any())->willReturn([]);

        $this->eventDispatcher = new EventDispatcher();
        foreach ([BeforeEntryDelete::class, EntryDeleted::class, PlacementsChanged::class] as $event) {
            $this->eventDispatcher->addListener($event, function (object $event): void {
                $this->events[] = $event;
                $this->calls[] = $event::class;
            });
        }
    }

    /**
     * @test
     */
    public function it_asks_listeners_then_deletes_the_entry_with_it_locked_and_tells_them_after_the_commit(): void
    {
        $this->deleter()->delete($this->page);

        self::assertSame(['begin', 'lock to write', 'read again', BeforeEntryDelete::class, 'remove', 'flush', 'commit', EntryDeleted::class], $this->calls);

        $before = $this->events[0];
        self::assertInstanceOf(BeforeEntryDelete::class, $before);
        self::assertSame($this->page, $before->entry);

        $deleted = $this->events[1];
        self::assertInstanceOf(EntryDeleted::class, $deleted);
        self::assertSame($this->page->getUuid(), $deleted->uuid);
        self::assertSame('page', $deleted->contentType);
    }

    /**
     * @test
     */
    public function it_tells_listeners_after_the_commit_which_slots_a_deleted_snippet_was_placed_in_reading_them_before_the_delete(): void
    {
        $snippet = $this->snippet();
        $placements = [new Placement($snippet, 'product.after_price', 0), new Placement($snippet, 'shop.footer', 2), new Placement($snippet, 'shop.footer', 5)];
        $calls = &$this->calls;
        $this->placements->findBySnippet($snippet)->will(static function () use ($placements, &$calls): array {
            $calls[] = 'read placements';

            return $placements;
        })->shouldBeCalledOnce();

        $this->deleter()->delete($snippet);

        self::assertSame(['begin', 'lock to write', 'read again', BeforeEntryDelete::class, 'read placements', 'remove', 'flush', 'commit', EntryDeleted::class, PlacementsChanged::class], $this->calls);

        $placementsChanged = $this->events[2];
        self::assertInstanceOf(PlacementsChanged::class, $placementsChanged);
        self::assertSame([$snippet->getUuid()], $placementsChanged->snippetUuids);
        self::assertSame(['product.after_price', 'shop.footer'], $placementsChanged->slots);
    }

    /**
     * @test
     */
    public function it_tells_no_one_about_placements_when_the_deleted_snippet_was_placed_nowhere(): void
    {
        $snippet = $this->snippet();

        $this->deleter()->delete($snippet);

        self::assertSame([BeforeEntryDelete::class, EntryDeleted::class], array_map(static fn (object $event): string => $event::class, $this->events));
    }

    /**
     * @test
     */
    public function it_reads_no_placements_of_an_entry_that_is_not_a_snippet(): void
    {
        $this->placements->findBySnippet(Argument::any())->shouldNotBeCalled();

        $this->deleter()->delete($this->page);
    }

    /**
     * @test
     */
    public function it_keeps_the_entry_when_a_listener_vetoes_the_delete(): void
    {
        $this->manager->remove(Argument::any())->shouldNotBeCalled();
        $this->eventDispatcher->addListener(BeforeEntryDelete::class, static fn (BeforeEntryDelete $event) => $event->veto('The homepage stays'), 10);

        try {
            $this->deleter()->delete($this->page);
            self::fail('The delete was not vetoed');
        } catch (DeleteVetoedException $e) {
            self::assertSame('The homepage stays', $e->getMessage());
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
    }

    /**
     * A snippet the entity manager deletes as it deletes the page
     */
    private function snippet(): Snippet
    {
        $snippet = new Snippet();
        $this->manager->lock($snippet, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $this->manager->refresh($snippet)->will($this->record('read again'));
        $this->manager->remove($snippet)->will($this->record('remove'));
        $this->managerRegistry->getManagerForClass(Snippet::class)->willReturn($this->manager->reveal());

        return $snippet;
    }

    private function deleter(): EntryDeleter
    {
        return new EntryDeleter(
            new EntryLock($this->managerRegistry->reveal()),
            $this->managerRegistry->reveal(),
            $this->eventDispatcher,
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
            ]),
            $this->placements->reveal(),
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
}
