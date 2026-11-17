<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Slot\OutdatedPlacementOrderException;
use Setono\SyliusCMSPlugin\Slot\PlacementEditorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Save of a capped slot's Order… dialog on the overview page of a snippet placed in it, which puts every placement
 * in the slot, posted as `slot` and `placements` (their UUIDs in their new order), of every snippet, in that order, and
 * comes back to the overview page. It takes effect at once (ADR 0005). An order made before the slot's placements
 * changed, such as in another tab, is refused. See "Placements" in docs/spec/admin.md
 */
final readonly class OrderPlacementsAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private PlacementRepositoryInterface $placements,
        private PlacementEditorInterface $placementEditor,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $slot = (string) $request->request->get('slot');
        $order = array_values(array_filter($request->request->all('placements'), is_string(...)));

        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($slot, $order): array {
            $placedThere = array_filter($this->placements->findBySnippet($entry), static fn (PlacementInterface $placement): bool => $placement->getSlot() === $slot);
            if ([] === $placedThere) {
                throw new NotFoundHttpException(sprintf('The entry %s has no placement in the slot "%s"', $entry->getUuid(), $slot));
            }

            try {
                $this->placementEditor->reorder($slot, $order);
            } catch (OutdatedPlacementOrderException) {
                return ['error', 'setono_sylius_cms.placements_order_outdated'];
            }

            return ['success', 'setono_sylius_cms.placements_ordered'];
        });
    }
}
