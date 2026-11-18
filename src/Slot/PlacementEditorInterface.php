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
     * Puts the slot's placements, of every snippet, in this order, as a slot's Order… dialog does
     *
     * @param list<string> $placementUuids the UUIDs of every placement in the slot, each once, in their new order
     *
     * @return bool false when they're in that order already, which changes nothing
     *
     * @throws OutdatedPlacementOrderException when the list doesn't name each of the slot's placements exactly once,
     *                                         such as when another tab added or removed one
     */
    public function reorder(string $slot, array $placementUuids): bool;
}
