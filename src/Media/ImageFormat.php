<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The format an image variant is output in: WebP, AVIF, or the source's own. See "Image processing" in
 * docs/spec/media.md and "Images" in docs/spec/extending.md
 */
enum ImageFormat: string
{
    case Webp = 'webp';
    case Avif = 'avif';
    case Original = 'original';

    /**
     * What a <source type> names, or null for the source's own format
     */
    public function mimeType(): ?string
    {
        return match ($this) {
            self::Webp => 'image/webp',
            self::Avif => 'image/avif',
            self::Original => null,
        };
    }
}
