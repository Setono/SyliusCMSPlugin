<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Imagine\Driver\InfoProvider;
use Imagine\Image\ImagineInterface;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageFormatsInterface;

/**
 * WebP and the original format always, and AVIF where the image library LiipImagine is configured with says it writes
 * it. An image library that can't say what it supports isn't trusted with AVIF
 */
final readonly class LiipImageFormats implements ImageFormatsInterface
{
    public function __construct(private ImagineInterface $imagine)
    {
    }

    public function supports(ImageFormat $format): bool
    {
        return match ($format) {
            ImageFormat::Original, ImageFormat::Webp => true,
            ImageFormat::Avif => $this->imagine instanceof InfoProvider && true === $this->imagine::getDriverInfo(false)?->isFormatSupported('avif'),
        };
    }
}
