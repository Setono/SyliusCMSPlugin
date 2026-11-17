<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Factory\PlacementFactoryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Writes each change in a transaction of its own, with the changedAt and the "Last edited" of the snippets it changes
 * the placements of, and dispatches PlacementsChanged once it commits. A move numbers the slot's placements 0, 1, 2…
 * in their new order, so placements at the same position, such as two added at once, get positions of their own
 */
final readonly class PlacementEditor implements PlacementEditorInterface
{
    /**
     * @param class-string<PlacementInterface> $placementClass the placement resource's model
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $placementClass,
        private PlacementRepositoryInterface $placements,
        private PlacementFactoryInterface $placementFactory,
        private SlotRegistryInterface $slots,
        private ClockInterface $clock,
        private EditRecorder $editRecorder,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function add(SnippetInterface $snippet, string $slot): PlacementInterface
    {
        if (!$this->slots->has($slot)) {
            throw SlotNotOfferedException::undeclared($slot);
        }

        if (!in_array($slot, array_map(static fn (SlotMetadata $offered): string => $offered->identifier, $this->slots->providing($snippet->getContext())), true)) {
            throw SlotNotOfferedException::withoutContext($slot);
        }

        $placement = $this->placementFactory->createFor($snippet, $slot, $this->placements->nextPosition($slot));

        $this->write([$snippet], $slot, static function (EntityManagerInterface $manager) use ($placement): void {
            $manager->persist($placement);
        });

        return $placement;
    }

    public function remove(PlacementInterface $placement): void
    {
        $this->write([$placement->getSnippet()], $placement->getSlot(), static function (EntityManagerInterface $manager) use ($placement): void {
            $manager->remove($placement);
        });
    }

    public function moveUp(PlacementInterface $placement): bool
    {
        return $this->move($placement, -1);
    }

    public function moveDown(PlacementInterface $placement): bool
    {
        return $this->move($placement, 1);
    }

    /**
     * @param -1|1 $by
     */
    private function move(PlacementInterface $placement, int $by): bool
    {
        $inSlot = $this->placements->findBySlots([$placement->getSlot()]);

        $index = null;
        foreach ($inSlot as $i => $candidate) {
            if ($candidate->getUuid() === $placement->getUuid()) {
                $index = $i;

                break;
            }
        }

        $neighbour = null === $index ? null : ($inSlot[$index + $by] ?? null);
        if (null === $index || null === $neighbour) {
            return false;
        }

        $inSlot[$index + $by] = $inSlot[$index];
        $inSlot[$index] = $neighbour;

        $this->write([$inSlot[$index + $by]->getSnippet(), $neighbour->getSnippet()], $placement->getSlot(), static function () use ($inSlot): void {
            foreach ($inSlot as $position => $inOrder) {
                $inOrder->setPosition($position);
            }
        });

        return true;
    }

    /**
     * @param non-empty-list<SnippetInterface> $snippets the snippets whose placements change
     * @param callable(EntityManagerInterface): void $change
     */
    private function write(array $snippets, string $slot, callable $change): void
    {
        $unique = [];
        foreach ($snippets as $snippet) {
            $unique[$snippet->getUuid()] = $snippet;
        }

        $manager = EntityManagers::of($this->managerRegistry, $this->placementClass);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        $manager->wrapInTransaction(function () use ($manager, $change, $unique, $now): void {
            $change($manager);

            foreach ($unique as $snippet) {
                $snippet->setChangedAt($now);
                $this->editRecorder->record($snippet);
            }

            $manager->flush();
        });

        // After the transaction commits
        $this->eventDispatcher->dispatch(new PlacementsChanged(array_keys($unique), [$slot]));
    }
}
