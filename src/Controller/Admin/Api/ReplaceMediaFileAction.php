<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\MediaFileReplacerInterface;
use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Replaces the media item's file with the one in `file`, which takes effect at once wherever it's used, and answers
 * with the media item. A refused file answers 400 with the message editors see. See "Replacing a file" in
 * docs/spec/media.md
 */
final readonly class ReplaceMediaFileAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private MediaLookup $lookup,
        private MediaFileReplacerInterface $replacer,
        private MediaItemAnswer $answer,
        private UploadLimit $uploadLimit,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request, $uuid): JsonResponse {
            $mediaItem = $this->lookup->mediaItem($uuid);

            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile) {
                // A request over PHP's post_max_size arrives without its file
                return BuilderRequest::badRequest('The request has no file in file');
            }

            try {
                $this->replacer->replace($mediaItem, ReceivedFile::of($file, $this->uploadLimit->bytes()), $file->getClientOriginalName());
            } catch (RefusedFileException $e) {
                return BuilderRequest::badRequest($e->reason->trans($this->translator));
            }

            return new JsonResponse(($this->answer)($mediaItem));
        });
    }
}
