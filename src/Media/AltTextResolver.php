<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

final readonly class AltTextResolver implements AltTextResolverInterface
{
    public function __construct(private FallbackChainInterface $fallbackChain)
    {
    }

    public function resolve(MediaItemInterface $mediaItem, string $localeCode): ?string
    {
        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $altText = $mediaItem->getAltText($locale);
            if (null !== $altText) {
                return $altText;
            }
        }

        return null;
    }
}
