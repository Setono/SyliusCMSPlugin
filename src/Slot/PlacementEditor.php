<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
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
 * the placements of, and dispatches PlacementsChanged once it commits. A move or a new order numbers the slot's
 * placements 0, 1, 2… in their new order, so placements at the same position, such as two added at once, get positions
 * of their own
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
        private DeclaredContextInterface $declaredContext,
        private PageScopeValidatorInterface $pageScopeValidator,
    ) {
    }

    public function add(SnippetInterface $snippet, string $slot): PlacementInterface
    {
        if (!$this->slots->has($slot)) {
            throw SlotNotOfferedException::undeclared($slot);
        }

        if (!in_array($slot, array_map(static fn (SlotMetadata $offered): string => $offered->identifier, $this->slots->providing($this->declaredContext->toPlace($snippet))), true)) {
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

    public function setPageScope(PlacementInterface $placement, array $pageScope): void
    {
        $errors = $this->pageScopeValidator->validate($placement->getSlot(), $pageScope);
        if ([] !== $errors) {
            throw new InvalidPageScopeException($errors);
        }

        $conditions = [];
        foreach ($pageScope as $value) {
            // The validator refuses what isn't a condition
            $condition = StoredCondition::fromValue($value);
            if (null !== $condition) {
                $conditions[] = $condition->toArray();
            }
        }

        $this->write([$placement->getSnippet()], $placement->getSlot(), static function () use ($placement, $conditions): void {
            $placement->setPageScope($conditions);
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

    public function reorder(string $slot, array $placementUuids): bool
    {
        $inSlot = [];
        foreach ($this->placements->findBySlots([$slot]) as $placement) {
            $inSlot[$placement->getUuid()] = $placement;
        }

        $ordered = [];
        foreach ($placementUuids as $uuid) {
            if (!isset($inSlot[$uuid]) || isset($ordered[$uuid])) {
                throw new OutdatedPlacementOrderException($slot);
            }

            $ordered[$uuid] = $inSlot[$uuid];
        }

        if (count($ordered) !== count($inSlot)) {
            throw new OutdatedPlacementOrderException($slot);
        }

        $ordered = array_values($ordered);
        $moved = [];
        foreach ($ordered as $position => $placement) {
            if ($placement->getPosition() !== $position) {
                $moved[] = $placement;
            }
        }

        if ([] === $moved) {
            return false;
        }

        $this->write(array_map(static fn (PlacementInterface $placement): SnippetInterface => $placement->getSnippet(), $moved), $slot, static function () use ($ordered): void {
            foreach ($ordered as $position => $placement) {
                $placement->setPosition($position);
            }
        });

        return true;
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
