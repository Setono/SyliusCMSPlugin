<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves a translation key with the translator, when the locale's catalogue has it, or the catalogue of its language,
 * such as en for en_US, which is where translation files usually put it. The catalogues the translator falls back to
 * after that, such as the store's fallback locale's, don't count
 */
final readonly class TranslationKeyResolver implements TranslationKeyResolverInterface
{
    public function __construct(private TranslatorInterface&TranslatorBagInterface $translator)
    {
    }

    public function resolve(TranslationKey $key, string $locale): ?string
    {
        $catalogue = $this->translator->getCatalogue($locale);
        while (!$catalogue->defines($key->key, $key->domain)) {
            $fallback = $catalogue->getFallbackCatalogue();
            if (!$fallback instanceof MessageCatalogueInterface || !self::isLanguageOf($fallback->getLocale(), $locale)) {
                return null;
            }

            $catalogue = $fallback;
        }

        return $this->translator->trans($key->key, [], $key->domain, $locale);
    }

    /**
     * Whether a catalogue's locale is the locale's language, or one between them, such as en or en_US for en_US
     */
    private static function isLanguageOf(string $catalogue, string $locale): bool
    {
        return str_starts_with($locale, $catalogue . '_');
    }
}
