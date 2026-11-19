<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;

/**
 * Where each placement sits in its slot, as an export records it: the placements before it in the source slot's order,
 * from every snippet, nearest first. An import puts a placement right after the nearest of them the target also has, and
 * otherwise first in the slot. See "Placements" in docs/spec/transfer.md
 *
 * @internal
 */
final class PrecedingPlacements
{
    private function __construct()
    {
    }

    /**
     * @param iterable<PlacementInterface> $placements every placement of the slots, in any order
     *
     * @return array<string, list<string>> the UUIDs of the placements before each one in its slot, nearest first, by its UUID
     */
    public static function of(iterable $placements): array
    {
        $slots = [];
        foreach ($placements as $placement) {
            $slots[$placement->getSlot()][] = $placement;
        }

        $preceding = [];
        foreach ($slots as $slot) {
            usort($slot, static fn (PlacementInterface $a, PlacementInterface $b): int => $a->getPosition() <=> $b->getPosition());

            $before = [];
            foreach ($slot as $placement) {
                $preceding[$placement->getUuid()] = $before;
                array_unshift($before, $placement->getUuid());
            }
        }

        return $preceding;
    }
}
