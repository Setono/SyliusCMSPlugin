<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;

/**
 * Who a placement wins its slot for, as the Placements section of its snippet's overview page says it: its snippet's
 * audience, on the pages its page scope matches, where the placements before it leave it room. Decided as the shop
 * decides, without rendering, but without a page or a shopper: only a catch-all placement before it is sure to match.
 * See "Placements" in docs/spec/admin.md and "Capped slots" in docs/spec/targeting.md
 */
final readonly class WinsFor
{
    /**
     * @param Audience $audience its snippet's audience
     * @param int|null $cap its slot's cap, or null for a slot that isn't capped
     */
    public function __construct(
        public Audience $audience,
        public WinsWhen $when,
        public ?int $cap,
    ) {
    }

    /**
     * Who the placement at this index of its slot's placements wins for
     *
     * @param list<PlacementInterface> $inSlot every placement in the slot, of every snippet, in the slot's order
     */
    public static function at(array $inSlot, int $index, ?int $cap): self
    {
        $audience = $inSlot[$index]->getSnippet()->getAudience();
        if (null === $cap || $index < $cap) {
            return new self($audience, WinsWhen::Matching, $cap);
        }

        $catchAllsAbove = count(array_filter(array_slice($inSlot, 0, $index), static fn (PlacementInterface $placement): bool => $placement->isCatchAll()));

        return new self($audience, $catchAllsAbove >= $cap ? WinsWhen::Never : WinsWhen::FewerAboveMatch, $cap);
    }
}
