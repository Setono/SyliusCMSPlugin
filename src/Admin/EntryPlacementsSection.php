<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * What the Placements section of a snippet's overview page shows: its placements, and the slots Add placement offers
 */
final readonly class EntryPlacementsSection
{
    /**
     * @param list<EntryPlacement> $placements in the order the slots are declared, those in slots the code no longer
     *                                         declares last, and in each slot's order
     * @param list<SlotMetadata> $offeredSlots the slots that provide at least the context the snippet needs
     */
    public function __construct(
        public array $placements,
        public array $offeredSlots,
    ) {
    }
}
