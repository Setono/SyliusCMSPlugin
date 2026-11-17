<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * A media item's alt text in a locale, falling back through the fallback chain when it has none there.
 * See "Metadata" in docs/spec/media.md
 */
interface AltTextResolverInterface
{
    /**
     * @return string|null null when it has alt text in no locale of the chain
     */
    public function resolve(MediaItemInterface $mediaItem, string $localeCode): ?string;
}
