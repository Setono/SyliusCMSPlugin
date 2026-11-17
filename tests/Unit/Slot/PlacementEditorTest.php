<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Factory\PlacementFactory;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Slot\PlacementEditor;
use Setono\SyliusCMSPlugin\Slot\SlotNotOfferedException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PlacementEditorTest extends TestCase
{
    use ProphecyTrait;

    private const NOW = '2026-11-14T11:00:00+00:00';

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    private EventDispatcher $eventDispatcher;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $calls = &$this->calls;
        $this->manager->wrapInTransaction(Argument::type('callable'))->will(static function (array $arguments) use (&$calls): mixed {
            $transaction = $arguments[0] ?? null;
            self::assertIsCallable($transaction);
            $calls[] = 'begin';
            $result = $transaction();
            $calls[] = 'commit';

            return $result;
        });
        $this->manager->persist(Argument::type(PlacementInterface::class))->will(static function (array $arguments) use (&$calls): void {
            $placement = $arguments[0];
            self::assertInstanceOf(PlacementInterface::class, $placement);
            $calls[] = sprintf('persist %s at %d', $placement->getSlot(), $placement->getPosition());
        });
        $this->manager->remove(Argument::type(PlacementInterface::class))->will(static function (array $arguments) use (&$calls): void {
            $placement = $arguments[0];
            self::assertInstanceOf(PlacementInterface::class, $placement);
            $calls[] = sprintf('remove %s at %d', $placement->getSlot(), $placement->getPosition());
        });
        $this->manager->flush()->will(static function () use (&$calls): void {
            $calls[] = 'flush';
        });

        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);

        $this->eventDispatcher = new EventDispatcher();
        $this->eventDispatcher->addListener(PlacementsChanged::class, function (PlacementsChanged $event): void {
            $this->calls[] = sprintf('placements changed: %s in %s', implode(', ', $event->snippetUuids), implode(', ', $event->slots));
        });
    }

    /**
     * @test
     */
    public function it_adds_a_placement_after_the_slot_s_others_bumps_the_snippet_s_changed_at_and_dispatches_placements_changed_after_the_commit(): void
    {
        $snippet = new Snippet();
        $this->placements->nextPosition('shop.footer')->willReturn(3);

        $placement = $this->editor()->add($snippet, 'shop.footer');

        self::assertSame($snippet, $placement->getSnippet());
        self::assertSame('shop.footer', $placement->getSlot());
        self::assertSame(3, $placement->getPosition());
        self::assertSame([
            'begin',
            'persist shop.footer at 3',
            'flush',
            'commit',
            sprintf('placements changed: %s in shop.footer', $snippet->getUuid()),
        ], $this->calls);
        self::assertSame(self::NOW, $snippet->getChangedAt()->format('Y-m-d\TH:i:sP'));
        self::assertSame('jane', $snippet->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_adds_a_placement_only_in_a_slot_that_provides_at_least_the_snippet_s_context(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product']);
        $this->placements->nextPosition('product.after_price')->willReturn(0);

        self::assertSame('product.after_price', $this->editor()->add($snippet, 'product.after_price')->getSlot());

        $this->expectException(SlotNotOfferedException::class);
        $this->expectExceptionMessage('The slot "shop.footer" doesn\'t provide the context the snippet needs');

        $this->editor()->add($snippet, 'shop.footer');
    }

    /**
     * @test
     */
    public function it_adds_no_placement_in_a_slot_the_code_does_not_declare(): void
    {
        $this->expectException(SlotNotOfferedException::class);
        $this->expectExceptionMessage('There is no slot "shop.gone"');

        try {
            $this->editor()->add(new Snippet(), 'shop.gone');
        } finally {
            self::assertSame([], $this->calls);
        }
    }

    /**
     * @test
     */
    public function it_removes_a_placement_bumps_the_snippet_s_changed_at_and_dispatches_placements_changed_after_the_commit(): void
    {
        $snippet = new Snippet();
        $placement = new Placement($snippet, 'shop.footer', 2);

        $this->editor()->remove($placement);

        self::assertSame([
            'begin',
            'remove shop.footer at 2',
            'flush',
            'commit',
            sprintf('placements changed: %s in shop.footer', $snippet->getUuid()),
        ], $this->calls);
        self::assertSame(self::NOW, $snippet->getChangedAt()->format('Y-m-d\TH:i:sP'));
    }

    /**
     * @test
     */
    public function it_moves_a_placement_up_past_another_snippet_s_placement_numbering_the_slot_s_placements_in_their_new_order(): void
    {
        $footer = new Snippet();
        $other = new Snippet();
        $first = new Placement($footer, 'shop.footer', 0);
        $second = new Placement($other, 'shop.footer', 4);
        $third = new Placement($footer, 'shop.footer', 9);
        $this->placements->findBySlots(['shop.footer'])->willReturn([$first, $second, $third]);

        self::assertTrue($this->editor()->moveUp($third));

        self::assertSame([[0, 0], [1, 2], [2, 1]], [[0, $first->getPosition()], [1, $second->getPosition()], [2, $third->getPosition()]]);
        self::assertSame([
            'begin',
            'flush',
            'commit',
            sprintf('placements changed: %s, %s in shop.footer', $footer->getUuid(), $other->getUuid()),
        ], $this->calls);
        self::assertSame(self::NOW, $footer->getChangedAt()->format('Y-m-d\TH:i:sP'));
        self::assertSame(self::NOW, $other->getChangedAt()->format('Y-m-d\TH:i:sP'));
    }

    /**
     * @test
     */
    public function it_moves_a_placement_down_past_the_next_one(): void
    {
        $snippet = new Snippet();
        $first = new Placement($snippet, 'shop.footer', 0);
        $second = new Placement($snippet, 'shop.footer', 1);
        $this->placements->findBySlots(['shop.footer'])->willReturn([$first, $second]);

        self::assertTrue($this->editor()->moveDown($first));

        self::assertSame([1, 0], [$first->getPosition(), $second->getPosition()]);
        self::assertSame(sprintf('placements changed: %s in shop.footer', $snippet->getUuid()), $this->calls[3] ?? null);
    }

    /**
     * @test
     */
    public function it_leaves_the_first_placement_first_and_the_last_last_and_changes_nothing(): void
    {
        $snippet = new Snippet();
        $changedAt = $snippet->getChangedAt();
        $first = new Placement($snippet, 'shop.footer', 0);
        $last = new Placement($snippet, 'shop.footer', 1);
        $this->placements->findBySlots(['shop.footer'])->willReturn([$first, $last]);

        self::assertFalse($this->editor()->moveUp($first));
        self::assertFalse($this->editor()->moveDown($last));

        self::assertSame([0, 1], [$first->getPosition(), $last->getPosition()]);
        self::assertSame([], $this->calls);
        self::assertSame($changedAt, $snippet->getChangedAt());
    }

    private function editor(): PlacementEditor
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Placement::class)->willReturn($this->manager->reveal());
        $clock = new MockClock(new \DateTimeImmutable('2026-11-14 12:00:00', new \DateTimeZone('Europe/Copenhagen')));
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('jane', null), 'admin', []));

        return new PlacementEditor(
            $managerRegistry->reveal(),
            Placement::class,
            $this->placements->reveal(),
            new PlacementFactory(Placement::class),
            new SlotRegistry([
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
            $clock,
            new EditRecorder($tokenStorage, $clock),
            $this->eventDispatcher,
        );
    }
}
