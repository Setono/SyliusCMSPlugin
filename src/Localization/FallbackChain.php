<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * Falls back from a locale through the locales configured for it under setono_sylius_cms.fallback_locales, in order,
 * and then to the store's fallback locale, Sylius's locale parameter that product translations fall back to.
 * See "Fallback chain" in docs/spec/localization.md
 */
final readonly class FallbackChain implements FallbackChainInterface
{
    /**
     * @param array<string, list<string>> $fallbackLocales locale code => the locales it falls back through, in order
     */
    public function __construct(
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private array $fallbackLocales = [],
    ) {
    }

    public function localesFor(string $localeCode): array
    {
        $locales = [$localeCode, ...$this->fallbackLocales[$localeCode] ?? [], $this->translationLocaleProvider->getDefaultLocaleCode()];

        // Each locale where the chain first reaches it
        return array_values(array_unique($locales));
    }
}
