<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\EditorPresetDeleterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Deletes an editor preset, at once, from the builder's Presets tab. The entries it was inserted into stay as they are.
 * Answers 204, or 404 when there's no such editor preset. See "Editor presets" in docs/spec/layouts-and-presets.md and
 * "JSON endpoints" in docs/spec/builder.md
 */
final readonly class DeletePresetAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private EditorPresetDeleterInterface $deleter,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($uuid): JsonResponse {
            if (!$this->deleter->delete($uuid)) {
                throw new NotFoundHttpException(sprintf('There is no editor preset %s', $uuid));
            }

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        });
    }
}
