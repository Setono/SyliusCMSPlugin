<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\InvalidMediaChangeException;
use Setono\SyliusCMSPlugin\Media\MediaFolderModifierInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates a folder of the media library from the body, `{name, parent}`, a parent of null being the top, and answers
 * with it
 */
final readonly class CreateMediaFolderAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private MediaLookup $lookup,
        private MediaFolderModifierInterface $modifier,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request): JsonResponse {
            try {
                $body = $request->toArray();
                $folder = $this->modifier->create(BodyValue::string($body['name'] ?? null, 'name'), $this->lookup->folderIn($body['parent'] ?? null, 'parent'));
            } catch (JsonException|InvalidMediaChangeException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            return new JsonResponse(MediaFolderAnswer::of($folder), Response::HTTP_CREATED);
        });
    }
}
