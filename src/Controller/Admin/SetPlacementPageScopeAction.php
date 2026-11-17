<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Slot\InvalidPageScopeException;
use Setono\SyliusCMSPlugin\Slot\PlacementEditorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The overview page's Edit page scope of one of the snippet's placements, which saves the conditions posted as `scope`,
 * a JSON list in the stored condition format, and comes back to the overview page. A scope that can't be saved is
 * kept as it was, and the flash message says why. It takes effect at once (ADR 0005). See "Placements" in
 * docs/spec/admin.md and "Page scopes" in docs/spec/slots.md
 */
final readonly class SetPlacementPageScopeAction
{
    public function __construct(
        private SnippetPlacementRequest $placementRequest,
        private PlacementEditorInterface $placementEditor,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid, string $placement): RedirectResponse
    {
        $scope = json_decode((string) $request->request->get('scope'), true);

        return $this->placementRequest->submit($request, $uuid, $placement, function (PlacementInterface $placement) use ($scope): array {
            if (!is_array($scope) || !array_is_list($scope)) {
                return ['error', 'setono_sylius_cms.page_scope_not_a_list'];
            }

            try {
                $this->placementEditor->setPageScope($placement, $scope);
            } catch (InvalidPageScopeException $e) {
                return ['error', ['message' => 'setono_sylius_cms.page_scope_invalid', 'parameters' => [
                    '%errors%' => implode(' ', array_map(fn (TranslatableMessage $error): string => $error->trans($this->translator), $e->errors)),
                ]]];
            }

            return ['success', 'setono_sylius_cms.page_scope_saved'];
        });
    }
}
