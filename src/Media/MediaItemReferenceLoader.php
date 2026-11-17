<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;

/**
 * Loads the media items references name, by UUID. Media items are shared by every channel and public, so every one
 * that exists resolves for every shopper. See "What the library holds" in docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaItemReferenceLoader implements ReferenceTypeLoaderInterface
{
    public function __construct(private MediaItemRepositoryInterface $mediaItemRepository)
    {
    }

    public function load(array $ids, RenderContext $context): array
    {
        $mediaItems = [];
        foreach ($this->mediaItemRepository->findByUuids($ids) as $mediaItem) {
            $mediaItems[$mediaItem->getUuid()] = $mediaItem;
        }

        return $mediaItems;
    }

    public function existing(array $ids): array
    {
        return $this->mediaItemRepository->findExistingUuids($ids);
    }
}
