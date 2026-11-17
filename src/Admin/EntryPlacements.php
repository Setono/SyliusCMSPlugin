<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
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
 * cms_admin_entry_placements(), each with its place among its slot's placements of every snippet, its page scope and the
 * condition kinds its page scope may hold, and the slots Add placement offers. See "Placements" in docs/spec/admin.md
 */
final readonly class EntryPlacements
{
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private DeclaredContextInterface $declaredContext,
        private ConditionKindRegistryInterface $conditionKinds,
        private PageScopeSummaries $pageScopes,
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

        /** @var array<string, list<string>> $inSlots slot identifier => the UUIDs of its placements of every snippet, in order */
        $inSlots = [];
        if ([] !== $slots) {
            foreach ($this->placements->findBySlots($slots) as $placement) {
                $inSlots[$placement->getSlot()][] = $placement->getUuid();
            }
        }

        $pageScopes = $this->pageScopes->of($placements);

        $listed = array_map(function (PlacementInterface $placement) use ($inSlots, $pageScopes): EntryPlacement {
            $inSlot = $inSlots[$placement->getSlot()] ?? [$placement->getUuid()];
            $index = array_search($placement->getUuid(), $inSlot, true);
            $slot = $this->slots->has($placement->getSlot()) ? $this->slots->get($placement->getSlot()) : null;

            return new EntryPlacement(
                $placement,
                $slot,
                false === $index ? count($inSlot) : $index + 1,
                count($inSlot),
                $pageScopes[$placement->getUuid()] ?? [],
                null === $slot ? [] : $this->scopeKinds($slot),
            );
        }, $placements);

        return new EntryPlacementsSection(self::inDeclaredOrder($listed, $this->slots->all()), $this->slots->providing($this->declaredContext->toPlace($entry)));
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
            array_filter($this->conditionKinds->all(), static fn (ConditionKindMetadata $kind): bool => $kind->readsHostPage() && $slot->providesAll($kind->needs)),
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
