<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The formats the image provider serves variants in, which cms_image() offers only then, such as AVIF where the server's
 * image library writes it. The default asks the image library LiipImagine is configured with, so a project that swaps the
 * image provider for an image CDN aliases this interface too. See "Images" in docs/spec/extending.md
 */
interface ImageFormatsInterface
{
    public function supports(ImageFormat $format): bool;
}
