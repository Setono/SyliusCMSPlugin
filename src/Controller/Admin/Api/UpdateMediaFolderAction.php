<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\InvalidMediaChangeException;
use Setono\SyliusCMSPlugin\Media\MediaFolderModifierInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renames a folder of the media library (`{name}`), or moves it into another folder or to the top (`{parent}`, a
 * folder's UUID or null), and answers with it. Folders exist only in the library, so no file moves
 */
final readonly class UpdateMediaFolderAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private MediaFolderRequest $folderRequest,
        private MediaFolderRepositoryInterface $folderRepository,
        private MediaFolderModifierInterface $modifier,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request, $uuid): JsonResponse {
            $folder = $this->folderRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no media folder %s', $uuid));

            try {
                $body = $request->toArray();
                if (array_key_exists('name', $body)) {
                    $this->modifier->rename($folder, MediaFolderRequest::string($body['name'], 'name'));
                }

                if (array_key_exists('parent', $body)) {
                    $this->modifier->move($folder, $this->folderRequest->parent($body['parent']));
                }
            } catch (JsonException|InvalidMediaChangeException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            return new JsonResponse(MediaFolderRequest::answer($folder));
        });
    }
}
