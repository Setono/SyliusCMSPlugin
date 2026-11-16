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
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\DeleteVetoedException;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleter;
use Setono\SyliusCMSPlugin\Publishing\EntryLock;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntryDeleterTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

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

        $this->eventDispatcher = new EventDispatcher();
        foreach ([BeforeEntryDelete::class, EntryDeleted::class] as $event) {
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

    private function deleter(): EntryDeleter
    {
        return new EntryDeleter(
            new EntryLock($this->managerRegistry->reveal()),
            $this->managerRegistry->reveal(),
            $this->eventDispatcher,
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            ]),
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
