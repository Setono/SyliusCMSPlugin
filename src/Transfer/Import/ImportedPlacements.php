<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Factory\PlacementFactoryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;
use Setono\SyliusCMSPlugin\Transfer\PrecedingPlacements;

/**
 * Gives an imported snippet the export's placements, which replace its placements in the target and take effect at
 * once, as a save of placements does. Each keeps its UUID, and goes right after the nearest of its preceding
 * placements the slot has, and otherwise first in it. A slot the code no longer declares is kept, and flagged as
 * everywhere else. It runs in the import's transaction, so PlacementsChanged goes out once that commits. See
 * "Placements" in docs/spec/transfer.md
 *
 * @internal
 */
final readonly class ImportedPlacements
{
    public function __construct(
        private EntityManagerInterface $manager,
        private PlacementRepositoryInterface $placements,
        private PlacementFactoryInterface $placementFactory,
        private EditRecorder $editRecorder,
        private AfterCommitDispatcher $events,
    ) {
    }

    /**
     * @param list<ExportedPlacement> $exported the snippet's placements in the export, by slot and then in each slot's
     *                                          order
     */
    public function replace(SnippetInterface $snippet, array $exported): void
    {
        $current = $this->placements->findBySnippet($snippet);
        if ([] === $current && [] === $exported) {
            return;
        }

        // The snippet's own placements are taken out first, so one that moves to another slot keeps its UUID
        $slots = [];
        foreach ($current as $placement) {
            $slots[$placement->getSlot()] = true;
            $this->manager->remove($placement);
        }

        $this->manager->flush();

        $bySlot = [];
        foreach ($exported as $placement) {
            $bySlot[$placement->slot][] = $placement;
            $slots[$placement->slot] = true;
        }

        foreach ($bySlot as $slot => $placements) {
            $this->place($snippet, (string) $slot, $placements);
        }

        $this->editRecorder->record($snippet);
        $this->manager->flush();

        $this->events->dispatch(new PlacementsChanged([$snippet->getUuid()], array_map('strval', array_keys($slots))));
    }

    /**
     * @param non-empty-list<ExportedPlacement> $exported
     */
    private function place(SnippetInterface $snippet, string $slot, array $exported): void
    {
        $inSlot = $this->placements->findBySlots([$slot]);
        usort($inSlot, static fn (PlacementInterface $a, PlacementInterface $b): int => $a->getPosition() <=> $b->getPosition());

        $byUuid = [];
        foreach ($inSlot as $placement) {
            $byUuid[$placement->getUuid()] = $placement;
        }

        $order = PrecedingPlacements::place(array_keys($byUuid), $exported);

        foreach ($exported as $placement) {
            $created = $this->placementFactory->createFor($snippet, $slot, 0);
            $created->setUuid($placement->uuid);
            $created->setPageScope($placement->scope);
            $this->manager->persist($created);
            $byUuid[$placement->uuid] = $created;
        }

        // The slot's placements are numbered 0, 1, 2… in their new order
        foreach ($order as $position => $uuid) {
            $byUuid[$uuid]->setPosition($position);
        }
    }
}
