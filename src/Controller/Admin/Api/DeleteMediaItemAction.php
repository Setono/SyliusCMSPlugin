<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\MediaItemDeleterInterface;
use Setono\SyliusCMSPlugin\Media\MediaItemDeleteVetoedException;
use Setono\SyliusCMSPlugin\Media\MediaItemInUseException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Deletes a media item that nothing uses, with its file and its variants, and answers 204. One in use answers 409 with
 * how many entries use it, and one a listener vetoed answers 422 with the listener's message. See "Deleting" in
 * docs/spec/media.md
 */
final readonly class DeleteMediaItemAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private MediaLookup $lookup,
        private MediaItemDeleterInterface $deleter,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($uuid): JsonResponse {
            $mediaItem = $this->lookup->mediaItem($uuid);

            try {
                $this->deleter->delete($mediaItem);
            } catch (MediaItemInUseException $e) {
                return new JsonResponse([
                    'error' => $this->translator->trans('setono_sylius_cms.media.in_use', ['%count%' => count($e->uses)]),
                ], Response::HTTP_CONFLICT);
            } catch (MediaItemDeleteVetoedException $e) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        });
    }
}
