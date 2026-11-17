<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A snippet's placements: each holds the snippet's declared context to the keys its slot provides, and needs those its
 * page scope's conditions need. A placement in a slot the code no longer declares is flagged on its own and limits
 * nothing. See "Snippets and their context" and "Page scopes" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class PlacementDependencies implements ContextDependencyFinderInterface
{
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private ConditionKindRegistryInterface $conditionKinds,
    ) {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        if (!$workingCopy instanceof SnippetInterface) {
            return;
        }

        foreach ($this->placements->findBySnippet($workingCopy) as $placement) {
            $slot = $this->slots->has($placement->getSlot()) ? $this->slots->get($placement->getSlot()) : null;

            yield new ContextDependency(
                new TranslatableMessage('setono_sylius_cms.context.placement', ['%slot%' => null === $slot ? $placement->getSlot() : new TranslatableMessage($slot->label)], 'validators'),
                $this->pageScopeNeeds($placement),
                $slot?->provides,
            );
        }
    }

    /**
     * The context keys the conditions of the placement's page scope need, of the kinds the code has
     *
     * @return list<string>
     */
    private function pageScopeNeeds(PlacementInterface $placement): array
    {
        $needs = [];
        foreach ($placement->getPageScope() as $condition) {
            $code = $condition['kind'] ?? null;
            if (is_string($code) && $this->conditionKinds->has($code)) {
                $needs = [...$needs, ...$this->conditionKinds->get($code)->needs];
            }
        }

        return array_values(array_unique($needs));
    }
}
