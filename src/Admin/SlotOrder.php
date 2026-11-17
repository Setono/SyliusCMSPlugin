<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * What the Order… dialog of a capped slot shows on the overview page of a snippet placed in it: every placement in the
 * slot, of every snippet, in the slot's order, to drag into order, and the slot's warnings. See "Placements" in
 * docs/spec/admin.md
 */
final readonly class SlotOrder
{
    /**
     * @param list<EntryPlacement> $placements every placement in the slot, of every snippet, in the slot's order
     * @param bool $withoutCatchAll whether none of them is a catch-all: one with an empty page scope whose snippet's
     *                              audience is everyone
     * @param list<string> $hiddenByHomepageIn the names of the channels whose homepage is a CMS page, which hides the
     *                                         slot when it's on Sylius's own homepage. Empty for any other slot
     */
    public function __construct(
        public SlotMetadata $slot,
        public array $placements,
        public bool $withoutCatchAll,
        public array $hiddenByHomepageIn,
    ) {
    }
}
