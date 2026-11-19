<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;

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

    /**
     * Where an import puts a snippet's placements in a slot: each right after the nearest of its preceding placements
     * the slot has, counting those placed before it, and otherwise first in the slot, so it never lands behind a capped
     * slot's catch-all
     *
     * @param list<string> $slot the UUIDs of the slot's placements in the target, in order, without the snippet's own
     * @param list<ExportedPlacement> $placements the snippet's placements in the slot, in the source slot's order
     *
     * @return list<string> the UUIDs of the slot's placements in their new order
     */
    public static function place(array $slot, array $placements): array
    {
        foreach ($placements as $placement) {
            $at = 0;
            foreach ($placement->preceding as $uuid) {
                $index = array_search($uuid, $slot, true);
                if (false !== $index) {
                    $at = $index + 1;

                    break;
                }
            }

            array_splice($slot, $at, 0, [$placement->uuid]);
        }

        return $slot;
    }
}
