<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Slot\PlacementEditorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Remove of one of the snippet's placements, which comes back to the overview page. It takes effect
 * at once (ADR 0005). See "Placements" in docs/spec/admin.md
 */
final readonly class RemovePlacementAction
{
    public function __construct(
        private SnippetPlacementRequest $placementRequest,
        private PlacementEditorInterface $placementEditor,
    ) {
    }

    public function __invoke(Request $request, string $uuid, string $placement): RedirectResponse
    {
        return $this->placementRequest->submit($request, $uuid, $placement, function (PlacementInterface $placement): array {
            $this->placementEditor->remove($placement);

            return ['success', 'setono_sylius_cms.placement_removed'];
        });
    }
}
