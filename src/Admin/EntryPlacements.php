<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;

/**
 * The placements of a snippet, which the Placements section of its overview page lists through
 * cms_admin_entry_placements(), each with its place among its slot's placements of every snippet, and the slots Add
 * placement offers. See "Placements" in docs/spec/admin.md
 */
final readonly class EntryPlacements
{
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private DeclaredContextInterface $declaredContext,
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

        $listed = array_map(function (PlacementInterface $placement) use ($inSlots): EntryPlacement {
            $inSlot = $inSlots[$placement->getSlot()] ?? [$placement->getUuid()];
            $index = array_search($placement->getUuid(), $inSlot, true);

            return new EntryPlacement(
                $placement,
                $this->slots->has($placement->getSlot()) ? $this->slots->get($placement->getSlot()) : null,
                false === $index ? count($inSlot) : $index + 1,
                count($inSlot),
            );
        }, $placements);

        return new EntryPlacementsSection(self::inDeclaredOrder($listed, $this->slots->all()), $this->slots->providing($this->declaredContext->toPlace($entry)));
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
