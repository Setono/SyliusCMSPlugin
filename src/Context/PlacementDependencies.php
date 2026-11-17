<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A snippet's placements: each holds the snippet's declared context to the keys its slot provides. Its page scope needs
 * nothing of the snippet: its conditions read the context the slot provides, which saving the scope checks, so a snippet
 * shown on any page can be scoped to products in a taxon in a product page's slot. A placement in a slot the code no
 * longer declares is flagged on its own and limits nothing. See "Snippets and their context" and "Page scopes" in
 * docs/spec/slots.md
 *
 * @internal
 */
final readonly class PlacementDependencies implements ContextDependencyFinderInterface
{
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
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
                [],
                $slot?->provides,
            );
        }
    }
}
