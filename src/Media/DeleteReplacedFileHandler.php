<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;

/**
 * Deletes the old file and its variants, unless a media item has that file again, such as when the replace ran inside a
 * project's own transaction that rolled back after the message was sent
 */
final readonly class DeleteReplacedFileHandler
{
    public function __construct(
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaFileRemoverInterface $remover,
    ) {
    }

    public function __invoke(DeleteReplacedFile $message): void
    {
        if (null !== $this->mediaItemRepository->findOneByPath($message->path)) {
            return;
        }

        $this->remover->remove($message->path);
    }
}
