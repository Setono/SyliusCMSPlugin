<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * What the Placements section of a snippet's overview page shows: its placements, the slots Add placement offers, and
 * the Order… dialogs of the slots it's placed in
 */
final readonly class EntryPlacementsSection
{
    /**
     * @param list<EntryPlacement> $placements in the order the slots are declared, those in slots the code no longer
     *                                         declares last, and in each slot's order
     * @param list<SlotMetadata> $offeredSlots the slots that provide at least the context the snippet needs
     * @param list<SlotOrder> $slotOrders the order of each slot the snippet is placed in that the code declares, for
     *                                    its Order… dialog, in the order the slots are declared
     */
    public function __construct(
        public array $placements,
        public array $offeredSlots,
        public array $slotOrders = [],
    ) {
    }
}
