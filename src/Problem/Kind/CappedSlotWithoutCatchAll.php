<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A capped slot with no catch-all placement, of any snippet: no placement with an empty page scope whose snippet's
 * audience is everyone, so pages or shoppers none of its placements match get nothing there. A warning on each snippet
 * placed in the slot, once a slot, fixed in the Placements section of its overview page, whose Order… dialog shows it
 * too. Writing a placement, or changing or deleting a snippet's audience, may give or take a slot its catch-all, so it
 * has every snippet placed in a capped slot counted again. A project may cap a slot on purpose, so setono:cms:lint
 * doesn't report it. See "Capped slots" in docs/spec/targeting.md and "Problems" in docs/spec/admin.md
 */
final readonly class CappedSlotWithoutCatchAll implements ReferenceProblemKindInterface
{
    public const CODE = 'capped_slot_without_catch_all';

    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
    ) {
    }

    public static function getReferencedClasses(): array
    {
        return [PlacementInterface::class, EntryInterface::class];
    }

    /**
     * Of an entry, only its audience makes its placements catch-alls or not, and every write of a placement counts
     */
    public static function getReferenceFields(): array
    {
        return [EntryInterface::class => 'audienceMode'];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        $snippets = [];
        foreach ($this->placements->findBySlots($this->cappedSlots()) as $placement) {
            $snippets[$placement->getSnippet()->getUuid()] ??= $placement->getSnippet();
        }

        return array_values($snippets);
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof SnippetInterface) {
            return;
        }

        $capped = array_values(array_intersect(
            $this->cappedSlots(),
            array_map(static fn (PlacementInterface $placement): string => $placement->getSlot(), $this->placements->findBySnippet($entry)),
        ));
        if ([] === $capped) {
            return;
        }

        $withCatchAll = [];
        foreach ($this->placements->findBySlots($capped) as $placement) {
            if ($placement->isCatchAll()) {
                $withCatchAll[$placement->getSlot()] = true;
            }
        }

        foreach ($capped as $identifier) {
            if (isset($withCatchAll[$identifier])) {
                continue;
            }

            $slot = $this->slots->get($identifier);

            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.capped_slot_without_catch_all', ['%slot%' => new TranslatableMessage($slot->label), '%cap%' => $slot->cap]),
                Fix::inPlacements($entry),
            );
        }
    }

    /**
     * The identifiers of the capped slots, in identifier order
     *
     * @return list<string>
     */
    private function cappedSlots(): array
    {
        $capped = array_map(
            static fn (SlotMetadata $slot): string => $slot->identifier,
            array_filter($this->slots->all(), static fn (SlotMetadata $slot): bool => null !== $slot->cap),
        );
        sort($capped);

        return $capped;
    }
}
