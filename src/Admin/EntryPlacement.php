<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * One of a snippet's placements as the Placements section of its overview page lists it, or one of a capped slot's as
 * its Order… dialog lists it
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
     * @param list<PageScopeCondition> $pageScope its page scope's conditions, worded, in order. Empty, it shows on every
     *                                            page its slot appears on
     * @param list<string> $scopeKinds the codes of the condition kinds its page scope may hold: those that read the
     *                                 host page and whose needs its slot provides, none in a slot the code no longer
     *                                 declares
     * @param WinsFor|null $winsFor who it wins its slot for, or null in a slot the code no longer declares
     */
    public function __construct(
        public PlacementInterface $placement,
        public ?SlotMetadata $slot,
        public int $position,
        public int $count,
        public array $pageScope = [],
        public array $scopeKinds = [],
        public ?WinsFor $winsFor = null,
    ) {
        $this->first = 1 === $position;
        $this->last = $position === $count;
    }
}
