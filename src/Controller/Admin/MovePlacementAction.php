<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Slot\PlacementEditorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Move up and Move down of one of the snippet's placements, posted as `direction`, which swaps it
 * with the placement before or after it in its slot, another snippet's too, and comes back to the overview page. It
 * takes effect at once (ADR 0005). See "Placements" in docs/spec/admin.md
 */
final readonly class MovePlacementAction
{
    public function __construct(
        private SnippetPlacementRequest $placementRequest,
        private PlacementEditorInterface $placementEditor,
    ) {
    }

    public function __invoke(Request $request, string $uuid, string $placement): RedirectResponse
    {
        $up = 'up' === $request->request->get('direction');

        return $this->placementRequest->submit($request, $uuid, $placement, function (PlacementInterface $placement) use ($up): array {
            $moved = $up ? $this->placementEditor->moveUp($placement) : $this->placementEditor->moveDown($placement);

            return $moved ? ['success', 'setono_sylius_cms.placement_moved'] : ['error', 'setono_sylius_cms.placement_not_moved'];
        });
    }
}
