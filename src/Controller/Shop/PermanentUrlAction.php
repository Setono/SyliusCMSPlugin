<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A document's or video's permanent URL: a temporary redirect to its current file, because a replace changes the
 * target. Only the UUID is looked up, so any filename answers. An unknown UUID, and an image, which has no permanent
 * URL, answer 404. See "Permanent URLs" in docs/spec/media.md
 */
final readonly class PermanentUrlAction
{
    public function __construct(
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaUrlGeneratorInterface $mediaUrlGenerator,
    ) {
    }

    public function __invoke(string $uuid): RedirectResponse
    {
        $mediaItem = $this->mediaItemRepository->findOneByUuid(strtolower($uuid));
        if (null === $mediaItem || MediaKind::Image === $mediaItem->getKind()) {
            throw new NotFoundHttpException(sprintf('No document or video has the UUID %s', $uuid));
        }

        return new RedirectResponse($this->mediaUrlGenerator->generate($mediaItem), RedirectResponse::HTTP_FOUND);
    }
}
