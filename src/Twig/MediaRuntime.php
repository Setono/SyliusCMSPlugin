<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Media\PermanentUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_media_url(): the permanent URL of a document or video, which links to it lead to, such as the Downloads block's.
 * See "Permanent URLs" in docs/spec/media.md
 */
final readonly class MediaRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private PermanentUrlGeneratorInterface $permanentUrlGenerator,
        private MediaItemRepositoryInterface $mediaItemRepository,
    ) {
    }

    /**
     * Null for null, a UUID no media item has, or an image, which has no permanent URL: cms_image() renders images
     *
     * @param MediaItemInterface|string|null $media a media item, or its UUID
     */
    public function url(MediaItemInterface|string|null $media): ?string
    {
        if (is_string($media)) {
            $media = Uuid::isValid($media) ? $this->mediaItemRepository->findOneByUuid(strtolower($media)) : null;
        }

        return null === $media ? null : $this->permanentUrlGenerator->generate($media);
    }
}
