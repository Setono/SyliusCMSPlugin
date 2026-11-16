<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionRestorerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Copies a revision into the draft, replacing what it held. It answers like entry_show, with the restored content, so
 * the builder loads it. While a scheduled publish locks the draft, it answers 423. See "Revisions" in
 * docs/spec/publishing.md
 */
final readonly class RestoreRevisionAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private RevisionRepositoryInterface $revisions,
        private RevisionRestorerInterface $restorer,
        private WorkingCopyAnswer $answer,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($id): JsonResponse {
            $revision = $this->revisions->findOneById((int) $id) ?? throw new NotFoundHttpException(sprintf('There is no revision %s', $id));

            try {
                return ($this->answer)($revision->getEntry(), $this->restorer->restore($revision));
            } catch (DraftLockedException $e) {
                return BuilderRequest::draftLocked($e);
            }
        });
    }
}
