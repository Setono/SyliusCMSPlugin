<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * One of a snippet's placements as the Placements section of its overview page lists it
 */
final readonly class EntryPlacement
{
    /** Whether it's first in its slot, so it can't move up */
    public bool $first;

    /** Whether it's last in its slot, so it can't move down */
    public bool $last;

    /**
     * @param SlotMetadata|null $slot its slot, or null when the code no longer declares it
     * @param int $position its place among the slot's placements of every snippet, from 1
     * @param int $count how many placements the slot has, of every snippet
     */
    public function __construct(
        public PlacementInterface $placement,
        public ?SlotMetadata $slot,
        public int $position,
        public int $count,
    ) {
        $this->first = 1 === $position;
        $this->last = $position === $count;
    }
}
