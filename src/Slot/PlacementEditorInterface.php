<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * Everything that changes placements goes through it. Placements take effect on save, outside the snippets' drafts
 * (ADR 0005): each change is written at once, bumps the changedAt of the snippets whose placements it changes, and
 * dispatches PlacementsChanged after it commits. See "Placements" in docs/spec/slots.md
 */
interface PlacementEditorInterface
{
    /**
     * Places the snippet in the slot, after the slot's placements
     *
     * @throws SlotNotOfferedException when the code doesn't declare the slot, or it doesn't provide at least the
     *                                 context the snippet declares
     */
    public function add(SnippetInterface $snippet, string $slot): PlacementInterface;

    public function remove(PlacementInterface $placement): void;

    /**
     * Replaces the placement's page scope, which limits it to the shop pages it matches. Empty, it shows on every page
     * the slot appears on
     *
     * @param array<mixed> $pageScope its conditions in the stored condition format, in order
     *
     * @throws InvalidPageScopeException when a condition isn't one a page scope of a placement in its slot may hold
     */
    public function setPageScope(PlacementInterface $placement, array $pageScope): void;

    /**
     * Swaps the placement with the one before it in its slot, which can be another snippet's
     *
     * @return bool false when it's first already, which changes nothing
     */
    public function moveUp(PlacementInterface $placement): bool;

    /**
     * Swaps the placement with the one after it in its slot, which can be another snippet's
     *
     * @return bool false when it's last already, which changes nothing
     */
    public function moveDown(PlacementInterface $placement): bool;
}
