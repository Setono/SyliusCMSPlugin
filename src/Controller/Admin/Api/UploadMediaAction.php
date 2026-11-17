<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\MediaUploaderInterface;
use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * media_upload: uploads the files in `files[]` into the folder whose UUID is in `folder`, or into Unsorted without one.
 * Each file is checked on its own, so the answer lists the new media items, the duplicates found (`{filename, mediaItem}`,
 * the existing media item the editor is offered instead) and the refused files (`{filename, error}`, with the message
 * naming the limit). With `copy` set, a duplicate is uploaded as a copy. See "Uploading" in docs/spec/media.md
 */
final readonly class UploadMediaAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private MediaUploaderInterface $uploader,
        private MediaFolderRepositoryInterface $folderRepository,
        private MediaItemAnswer $answer,
        private UploadLimit $uploadLimit,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, fn (): JsonResponse => $this->upload($request));
    }

    private function upload(Request $request): JsonResponse
    {
        $folder = $this->folder($request->request->get('folder'));
        $files = self::files($request->files->all()['files'] ?? null);
        if ([] === $files) {
            // A request over PHP's post_max_size arrives without its files
            return BuilderRequest::badRequest('The request has no files in files[]');
        }

        $copy = $request->request->getBoolean('copy');
        $answer = ['created' => [], 'duplicates' => [], 'refused' => []];
        foreach ($files as $file) {
            $filename = $file->getClientOriginalName();

            try {
                $result = $this->uploader->upload(ReceivedFile::of($file, $this->uploadLimit->bytes()), $filename, $folder, $copy);
            } catch (RefusedFileException $e) {
                $answer['refused'][] = ['filename' => $filename, 'error' => $e->reason->trans($this->translator)];

                continue;
            }

            if ($result->duplicate) {
                $answer['duplicates'][] = ['filename' => $filename, 'mediaItem' => ($this->answer)($result->mediaItem)];
            } else {
                $answer['created'][] = ($this->answer)($result->mediaItem);
            }
        }

        return new JsonResponse($answer);
    }

    /**
     * @throws NotFoundHttpException when there's no such folder
     */
    private function folder(mixed $uuid): ?MediaFolderInterface
    {
        if (!is_string($uuid) || '' === $uuid) {
            return null;
        }

        return $this->folderRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no media folder %s', $uuid));
    }

    /**
     * @return list<UploadedFile>
     */
    private static function files(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return is_array($files) ? array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
    }
}
