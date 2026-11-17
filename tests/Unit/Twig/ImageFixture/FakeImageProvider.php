<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig\ImageFixture;

use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\ImageVariants;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Gives a variant's URL as /{format}/{width}/{path}, or /{format}/{width}x{height}/{path} for one cropped to an aspect
 * ratio, and a media item served as uploaded its file's URL, /media/cms/{path}
 */
final readonly class FakeImageProvider implements ImageProviderInterface
{
    public function __construct(private bool $avif)
    {
    }

    public function url(MediaItemInterface $mediaItem, ImageTransform $transform): string
    {
        if (!ImageVariants::areMadeFor($mediaItem)) {
            return '/media/cms/' . $mediaItem->getPath();
        }

        $size = (string) $transform->width . (null === $transform->height() ? '' : 'x' . $transform->height());

        return sprintf('/%s/%s/%s', $transform->format->value, $size, $mediaItem->getPath());
    }

    public function supportsFormat(ImageFormat $format): bool
    {
        return ImageFormat::Avif !== $format || $this->avif;
    }
}
