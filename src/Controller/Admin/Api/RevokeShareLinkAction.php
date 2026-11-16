<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Revokes a share link by deleting it, so its token answers as for a shopper. See "Share links" in docs/spec/publishing.md
 */
final readonly class RevokeShareLinkAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private ShareLinkRepositoryInterface $shareLinks,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($id): JsonResponse {
            $shareLink = $this->shareLinks->findOneById((int) $id) ?? throw new NotFoundHttpException(sprintf('There is no share link %s', $id));
            $this->shareLinks->remove($shareLink);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        });
    }
}
