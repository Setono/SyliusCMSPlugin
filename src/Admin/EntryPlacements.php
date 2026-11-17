<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
use Setono\SyliusCMSPlugin\Homepage\CMSHomepageChannels;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;

/**
 * The placements of a snippet, which the Placements section of its overview page lists through
 * cms_admin_entry_placements(), each with its place among its slot's placements of every snippet, its page scope, the
 * condition kinds its page scope may hold and who it wins for, the slots Add placement offers, and for each capped slot
 * it's in, every placement in the slot and the slot's warnings, for its Order… dialog. See "Placements" in
 * docs/spec/admin.md
 */
final readonly class EntryPlacements
{
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private DeclaredContextInterface $declaredContext,
        private ConditionKindRegistryInterface $conditionKinds,
        private PageScopeSummaries $pageScopes,
        private CMSHomepageChannels $cmsHomepageChannels,
    ) {
    }

    /**
     * Null for an entry that isn't a snippet, which has no placements
     */
    public function of(EntryInterface $entry): ?EntryPlacementsSection
    {
        if (!$entry instanceof SnippetInterface) {
            return null;
        }

        $placements = $this->placements->findBySnippet($entry);

        $slots = array_values(array_unique(array_map(static fn (PlacementInterface $placement): string => $placement->getSlot(), $placements)));
        sort($slots);

        /** @var array<string, list<PlacementInterface>> $inSlots slot identifier => its placements of every snippet, in order */
        $inSlots = [];
        if ([] !== $slots) {
            foreach ($this->placements->findBySlots($slots) as $placement) {
                $inSlots[$placement->getSlot()][] = $placement;
            }
        }

        $cappedSlots = array_values(array_filter(
            $this->slots->all(),
            static fn (SlotMetadata $slot): bool => null !== $slot->cap && isset($inSlots[$slot->identifier]),
        ));

        // The other snippets' placements in the capped slots are worded with the snippet's own, for the Order… dialogs
        $pageScopes = $this->pageScopes->of([...$placements, ...array_merge(...array_map(static fn (SlotMetadata $slot): array => $inSlots[$slot->identifier] ?? [], $cappedSlots))]);

        $listed = array_map(fn (PlacementInterface $placement): EntryPlacement => $this->listed($placement, $inSlots[$placement->getSlot()] ?? [$placement], $pageScopes), $placements);

        $slotOrders = array_map(fn (SlotMetadata $slot): SlotOrder => new SlotOrder(
            $slot,
            array_map(fn (PlacementInterface $placement): EntryPlacement => $this->listed($placement, $inSlots[$slot->identifier] ?? [], $pageScopes), $inSlots[$slot->identifier] ?? []),
            [] === array_filter($inSlots[$slot->identifier] ?? [], static fn (PlacementInterface $placement): bool => $placement->isCatchAll()),
            $slot->isOnStoreHomepage() ? $this->cmsHomepageChannels->names() : [],
        ), $cappedSlots);

        return new EntryPlacementsSection(
            self::inDeclaredOrder($listed, $this->slots->all()),
            $this->slots->providing($this->declaredContext->toPlace($entry)),
            $slotOrders,
        );
    }

    /**
     * The placement with its place among the slot's placements, its page scope and who it wins for
     *
     * @param list<PlacementInterface> $inSlot every placement in its slot, of every snippet, in order
     * @param array<string, list<PageScopeCondition>> $pageScopes by placement UUID
     */
    private function listed(PlacementInterface $placement, array $inSlot, array $pageScopes): EntryPlacement
    {
        $index = null;
        foreach ($inSlot as $i => $candidate) {
            if ($candidate->getUuid() === $placement->getUuid()) {
                $index = $i;

                break;
            }
        }

        $slot = $this->slots->find($placement->getSlot());

        return new EntryPlacement(
            $placement,
            $slot,
            null === $index ? count($inSlot) : $index + 1,
            count($inSlot),
            $pageScopes[$placement->getUuid()] ?? [],
            null === $slot ? [] : $this->scopeKinds($slot),
            null === $slot || null === $index ? null : WinsFor::at($inSlot, $index, $slot->cap),
        );
    }

    /**
     * The codes of the condition kinds a page scope in the slot may hold: those that read the host page and whose
     * needs the slot provides. See "Page scopes" in docs/spec/slots.md
     *
     * @return list<string>
     */
    private function scopeKinds(SlotMetadata $slot): array
    {
        return array_values(array_map(
            static fn (ConditionKindMetadata $kind): string => $kind->code,
            array_filter($this->conditionKinds->all(), $slot->canScopeWith(...)),
        ));
    }

    /**
     * By the order the slots are declared, those in slots the code no longer declares last, keeping each slot's order
     *
     * @param list<EntryPlacement> $placements
     * @param list<SlotMetadata> $declared
     *
     * @return list<EntryPlacement>
     */
    private static function inDeclaredOrder(array $placements, array $declared): array
    {
        $order = array_flip(array_map(static fn (SlotMetadata $slot): string => $slot->identifier, $declared));

        usort($placements, static fn (EntryPlacement $a, EntryPlacement $b): int => [
            $order[$a->placement->getSlot()] ?? \PHP_INT_MAX,
            $a->placement->getSlot(),
            $a->position,
        ] <=> [
            $order[$b->placement->getSlot()] ?? \PHP_INT_MAX,
            $b->placement->getSlot(),
            $b->position,
        ]);

        return $placements;
    }
}
