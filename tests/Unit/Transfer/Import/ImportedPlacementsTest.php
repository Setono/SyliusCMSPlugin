<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Factory\PlacementFactoryInterface;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportedPlacements;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An imported snippet's placements replace its placements in the target, at once and keeping their UUIDs. Each goes
 * right after the nearest of its preceding placements the slot has, and otherwise first in it. See "Placements" in
 * docs/spec/transfer.md
 */
final class ImportedPlacementsTest extends TestCase
{
    use ProphecyTrait;

    private const AFTER_PRICE = 'product.after_price';

    private const FOOTER = 'layout.footer';

    private const NOW = '2026-11-20 08:00:00';

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    /** @var ObjectProphecy<EventDispatcherInterface> */
    private ObjectProphecy $eventDispatcher;

    /** @var list<string> what the import did to the manager, in order */
    private array $log = [];

    /** @var array<string, PlacementInterface> the placements it created, by UUID */
    private array $created = [];

    private Snippet $snippet;

    protected function setUp(): void
    {
        $this->snippet = new Snippet();
        $this->snippet->setLastEdited(new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone('UTC')), 'Someone');

        $log = &$this->log;
        $created = &$this->created;
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->manager->remove(Argument::type(PlacementInterface::class))->will(static function (array $args) use (&$log): void {
            \assert($args[0] instanceof PlacementInterface);
            $log[] = 'remove ' . $args[0]->getUuid();
        });
        $this->manager->persist(Argument::type(PlacementInterface::class))->will(static function (array $args) use (&$log, &$created): void {
            \assert($args[0] instanceof PlacementInterface);
            $log[] = 'persist ' . $args[0]->getUuid();
            $created[$args[0]->getUuid()] = $args[0];
        });
        $this->manager->flush()->will(static function () use (&$log): void {
            $log[] = 'flush';
        });

        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        $this->placements->findBySlots(Argument::type('array'))->willReturn([]);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    }

    /**
     * @test
     */
    public function it_does_nothing_for_a_snippet_without_placements_in_the_target_or_the_export(): void
    {
        $this->placements->findBySnippet($this->snippet)->willReturn([]);
        $this->eventDispatcher->dispatch(Argument::cetera())->shouldNotBeCalled();

        $this->importedPlacements()->replace($this->snippet, []);

        self::assertSame([], $this->log);
        self::assertSame('2026-11-01 08:00:00', $this->snippet->getLastEditedAt()->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function it_replaces_the_snippets_placements_with_the_exported_ones_keeping_their_uuids_and_page_scopes(): void
    {
        $current = new Placement($this->snippet, self::AFTER_PRICE, 0);
        $current->setUuid('0193a5be-7300-7b1c-8d2e-000000000001');
        $this->placements->findBySnippet($this->snippet)->willReturn([$current]);
        $scope = [['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['includeChildren' => true, 'taxons' => ['t_shirts']]]];
        $this->eventDispatcher->dispatch(new PlacementsChanged([$this->snippet->getUuid()], [self::AFTER_PRICE]), null)->shouldBeCalledOnce()->willReturnArgument(0);

        $this->importedPlacements()->replace($this->snippet, [
            new ExportedPlacement('0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e', self::AFTER_PRICE, $scope, []),
        ]);

        // Its own placements are taken out first, so one that moved keeps its UUID
        self::assertSame([
            'remove 0193a5be-7300-7b1c-8d2e-000000000001',
            'flush',
            'persist 0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e',
            'flush',
        ], $this->log);
        $placement = $this->created['0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e'];
        self::assertSame($this->snippet, $placement->getSnippet());
        self::assertSame(self::AFTER_PRICE, $placement->getSlot());
        self::assertSame($scope, $placement->getPageScope());
        self::assertSame(0, $placement->getPosition());
        // It takes effect as a save of placements does, recording the edit
        self::assertSame(self::NOW, $this->snippet->getLastEditedAt()->format('Y-m-d H:i:s'));
        self::assertNull($this->snippet->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_puts_each_placement_right_after_the_nearest_of_its_preceding_placements_the_slot_has_and_otherwise_first(): void
    {
        $this->placements->findBySnippet($this->snippet)->willReturn([]);
        $other = new Snippet();
        // The slot's placements in the target, which the repository finds in no particular order
        $first = self::placement($other, self::AFTER_PRICE, 0, 'a0000000-0000-7000-8000-000000000000');
        $second = self::placement($other, self::AFTER_PRICE, 1, 'b0000000-0000-7000-8000-000000000000');
        $catchAll = self::placement($other, self::AFTER_PRICE, 2, 'c0000000-0000-7000-8000-000000000000');
        $this->placements->findBySlots([self::AFTER_PRICE])->willReturn([$catchAll, $first, $second]);
        $this->placements->findBySlots([self::FOOTER])->willReturn([]);
        $this->eventDispatcher->dispatch(new PlacementsChanged([$this->snippet->getUuid()], [self::AFTER_PRICE, self::FOOTER]), null)->shouldBeCalledOnce()->willReturnArgument(0);

        $this->importedPlacements()->replace($this->snippet, [
            // The nearest preceding placement the target lacks, so the next nearest counts
            new ExportedPlacement('e1000000-0000-7000-8000-000000000000', self::AFTER_PRICE, [], ['d0000000-0000-7000-8000-000000000000', 'b0000000-0000-7000-8000-000000000000', 'a0000000-0000-7000-8000-000000000000']),
            // Right after the one placed before it
            new ExportedPlacement('e2000000-0000-7000-8000-000000000000', self::AFTER_PRICE, [], ['e1000000-0000-7000-8000-000000000000', 'b0000000-0000-7000-8000-000000000000']),
            // None the target has, so first, never behind the catch-all
            new ExportedPlacement('e3000000-0000-7000-8000-000000000000', self::AFTER_PRICE, [], ['d0000000-0000-7000-8000-000000000000']),
            new ExportedPlacement('e4000000-0000-7000-8000-000000000000', self::FOOTER, [], []),
        ]);

        self::assertSame(0, $this->created['e3000000-0000-7000-8000-000000000000']->getPosition());
        self::assertSame(1, $first->getPosition());
        self::assertSame(2, $second->getPosition());
        self::assertSame(3, $this->created['e1000000-0000-7000-8000-000000000000']->getPosition());
        self::assertSame(4, $this->created['e2000000-0000-7000-8000-000000000000']->getPosition());
        self::assertSame(5, $catchAll->getPosition());
        self::assertSame(self::FOOTER, $this->created['e4000000-0000-7000-8000-000000000000']->getSlot());
        self::assertSame(0, $this->created['e4000000-0000-7000-8000-000000000000']->getPosition());
    }

    /**
     * @test
     */
    public function it_tells_of_the_slots_the_snippet_left_as_well_as_those_it_is_placed_in(): void
    {
        $current = self::placement($this->snippet, self::AFTER_PRICE, 3, '0193a5be-7300-7b1c-8d2e-000000000001');
        $this->placements->findBySnippet($this->snippet)->willReturn([$current]);
        $this->eventDispatcher->dispatch(new PlacementsChanged([$this->snippet->getUuid()], [self::AFTER_PRICE, self::FOOTER]), null)->shouldBeCalledOnce()->willReturnArgument(0);

        // The placement moves to another slot, keeping its UUID
        $this->importedPlacements()->replace($this->snippet, [
            new ExportedPlacement('0193a5be-7300-7b1c-8d2e-000000000001', self::FOOTER, [], []),
        ]);

        self::assertSame(self::FOOTER, $this->created['0193a5be-7300-7b1c-8d2e-000000000001']->getSlot());
    }

    /**
     * @test
     */
    public function it_takes_out_every_placement_of_the_snippet_when_the_export_has_none(): void
    {
        $this->placements->findBySnippet($this->snippet)->willReturn([
            self::placement($this->snippet, self::AFTER_PRICE, 0, '0193a5be-7300-7b1c-8d2e-000000000001'),
            self::placement($this->snippet, self::FOOTER, 1, '0193a5be-7300-7b1c-8d2e-000000000002'),
        ]);
        $this->eventDispatcher->dispatch(new PlacementsChanged([$this->snippet->getUuid()], [self::AFTER_PRICE, self::FOOTER]), null)->shouldBeCalledOnce()->willReturnArgument(0);

        $this->importedPlacements()->replace($this->snippet, []);

        self::assertSame([
            'remove 0193a5be-7300-7b1c-8d2e-000000000001',
            'remove 0193a5be-7300-7b1c-8d2e-000000000002',
            'flush',
            'flush',
        ], $this->log);
        self::assertSame(self::NOW, $this->snippet->getLastEditedAt()->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function it_tells_of_the_change_only_once_the_imports_transaction_commits(): void
    {
        $this->placements->findBySnippet($this->snippet)->willReturn([]);
        $events = new AfterCommitDispatcher($this->eventDispatcher->reveal());
        $events->hold();

        $this->importedPlacements($events)->replace($this->snippet, [new ExportedPlacement('0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e', self::FOOTER, [], [])]);

        $this->eventDispatcher->dispatch(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->eventDispatcher->dispatch(new PlacementsChanged([$this->snippet->getUuid()], [self::FOOTER]), null)->shouldBeCalledOnce()->willReturnArgument(0);

        $events->release();
    }

    private function importedPlacements(?AfterCommitDispatcher $events = null): ImportedPlacements
    {
        $factory = $this->prophesize(PlacementFactoryInterface::class);
        $factory->createFor(Argument::type(SnippetInterface::class), Argument::type('string'), 0)->will(static function (array $args): PlacementInterface {
            [$snippet, $slot, $position] = $args;
            \assert($snippet instanceof SnippetInterface && is_string($slot) && is_int($position));

            return new Placement($snippet, $slot, $position);
        });

        return new ImportedPlacements(
            $this->manager->reveal(),
            $this->placements->reveal(),
            $factory->reveal(),
            new EditRecorder(new TokenStorage(), new MockClock(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')))),
            $events ?? new AfterCommitDispatcher($this->eventDispatcher->reveal()),
        );
    }

    private static function placement(SnippetInterface $snippet, string $slot, int $position, string $uuid): Placement
    {
        $placement = new Placement($snippet, $slot, $position);
        $placement->setUuid($uuid);

        return $placement;
    }
}
