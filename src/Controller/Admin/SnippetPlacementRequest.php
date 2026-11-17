<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What the forms of one of a snippet's placements on its overview page check, besides what EntryOverviewRequest
 * checks: that the placement the URL names is one of the snippet's
 *
 * @internal
 */
final readonly class SnippetPlacementRequest
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private PlacementRepositoryInterface $placements,
    ) {
    }

    /**
     * @param callable(PlacementInterface): array{'success'|'error', string} $change
     *
     * @throws NotFoundHttpException when the snippet has no such placement
     */
    public function submit(Request $request, string $uuid, string $placement, callable $change): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($placement, $change): array {
            $found = $this->placements->findOneByUuid($placement);
            if (null === $found || $found->getSnippet()->getUuid() !== $entry->getUuid()) {
                throw new NotFoundHttpException(sprintf('The entry %s has no placement %s', $entry->getUuid(), $placement));
            }

            return $change($found);
        });
    }
}
