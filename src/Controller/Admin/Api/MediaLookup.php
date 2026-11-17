<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\InvalidMediaChangeException;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The media items and folders the media endpoints are asked about by UUID: in their URL or query, which answer 404 for
 * one there's none of, and in a request body, which answers 400
 *
 * @internal
 */
final readonly class MediaLookup
{
    public function __construct(
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaFolderRepositoryInterface $folderRepository,
    ) {
    }

    /**
     * @throws NotFoundHttpException when there's no such media item
     */
    public function mediaItem(string $uuid): MediaItemInterface
    {
        return $this->mediaItemRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no media item %s', $uuid));
    }

    /**
     * @throws NotFoundHttpException when there's no such folder
     */
    public function folder(string $uuid): MediaFolderInterface
    {
        return $this->folderRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no media folder %s', $uuid));
    }

    /**
     * The media item a body names by its UUID under the name, or null for none
     *
     * @throws InvalidMediaChangeException when the value isn't a string, or there's no such media item
     */
    public function mediaItemIn(mixed $uuid, string $name): ?MediaItemInterface
    {
        if (null === $uuid) {
            return null;
        }

        $uuid = BodyValue::string($uuid, $name);

        return $this->mediaItemRepository->findOneByUuid($uuid) ?? throw new InvalidMediaChangeException(sprintf('There is no media item %s', $uuid));
    }

    /**
     * The folder a body names by its UUID under the name, or null for none: Unsorted, or the top of the library
     *
     * @throws InvalidMediaChangeException when the value isn't a string, or there's no such folder
     */
    public function folderIn(mixed $uuid, string $name): ?MediaFolderInterface
    {
        if (null === $uuid) {
            return null;
        }

        $uuid = BodyValue::string($uuid, $name);

        return $this->folderRepository->findOneByUuid($uuid) ?? throw new InvalidMediaChangeException(sprintf('There is no media folder %s', $uuid));
    }
}
