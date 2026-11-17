<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates the setono_sylius_cms_media route. Its filename is cosmetic, since only the UUID is looked up: the original
 * filename as a slug, with the extension of the current file
 */
final readonly class PermanentUrlGenerator implements PermanentUrlGeneratorInterface
{
    public const ROUTE = 'setono_sylius_cms_media';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function generate(MediaItemInterface $mediaItem): ?string
    {
        if (MediaKind::Image === $mediaItem->getKind()) {
            return null;
        }

        return $this->urlGenerator->generate(self::ROUTE, [
            'uuid' => $mediaItem->getUuid(),
            'filename' => StoragePath::filename($mediaItem->getOriginalFilename(), pathinfo($mediaItem->getPath(), \PATHINFO_EXTENSION)),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
