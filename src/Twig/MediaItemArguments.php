<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The media item a cms_* function is given: a media item, or its UUID, which it looks up
 *
 * @internal
 */
final readonly class MediaItemArguments
{
    public function __construct(private MediaItemRepositoryInterface $mediaItemRepository)
    {
    }

    /**
     * Null for null, a string that isn't the UUID of a media item, and a media item of another kind than the one asked for
     */
    public function mediaItem(MediaItemInterface|string|null $media, ?MediaKind $kind = null): ?MediaItemInterface
    {
        if (is_string($media)) {
            $media = Uuid::isValid($media) ? $this->mediaItemRepository->findOneByUuid(strtolower($media)) : null;
        }

        return null !== $media && (null === $kind || $kind === $media->getKind()) ? $media : null;
    }
}
