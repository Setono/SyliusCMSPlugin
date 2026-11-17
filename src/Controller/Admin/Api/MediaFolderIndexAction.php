<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Every folder of the media library, by name, with its parent's UUID, so the library builds its tree under All media and
 * Unsorted. See "Folders" in docs/spec/media.md
 */
final readonly class MediaFolderIndexAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private MediaFolderRepositoryInterface $folderRepository,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return new JsonResponse(['folders' => array_map(MediaFolderAnswer::of(...), $this->folderRepository->findAllByName())]);
    }
}
