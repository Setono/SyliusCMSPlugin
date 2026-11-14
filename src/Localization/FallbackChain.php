<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * Falls back from a locale straight to the store's fallback locale. The configurable middle of the chain
 * (setono_sylius_cms.fallback_locales) comes with translations (#232)
 */
final readonly class FallbackChain implements FallbackChainInterface
{
    public function __construct(private TranslationLocaleProviderInterface $translationLocaleProvider)
    {
    }

    public function localesFor(string $localeCode): array
    {
        $fallbackLocaleCode = $this->translationLocaleProvider->getDefaultLocaleCode();

        return $localeCode === $fallbackLocaleCode ? [$localeCode] : [$localeCode, $fallbackLocaleCode];
    }
}
