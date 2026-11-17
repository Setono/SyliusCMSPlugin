<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Slot\PlacementEditorInterface;
use Setono\SyliusCMSPlugin\Slot\SlotNotOfferedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The overview page's Add placement, which places the snippet in the slot picked, posted as `slot`, after the slot's
 * placements, and comes back to the overview page. It takes effect at once (ADR 0005). See "Placements" in
 * docs/spec/admin.md
 */
final readonly class AddPlacementAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private PlacementEditorInterface $placementEditor,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($request): array {
            if (!$entry instanceof SnippetInterface) {
                throw new NotFoundHttpException(sprintf('The entry %s is no snippet, so it has no placements', $entry->getUuid()));
            }

            try {
                $this->placementEditor->add($entry, (string) $request->request->get('slot'));
            } catch (SlotNotOfferedException) {
                return ['error', 'setono_sylius_cms.placement_slot_not_offered'];
            }

            return ['success', 'setono_sylius_cms.placement_added'];
        });
    }
}
