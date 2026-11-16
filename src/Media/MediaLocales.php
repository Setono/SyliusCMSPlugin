<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * The store's locales, which media items have alt text in: Sylius's defined locales, the default first, then the others
 * by code, as the media library's screen and the builder's media picker list them. See "Metadata" in docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaLocales
{
    /**
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private RepositoryInterface $localeRepository,
    ) {
    }

    /**
     * Each with its name in the display locale, such as the admin user's
     *
     * @return list<array{code: string, name: string}>
     */
    public function all(string $displayLocale): array
    {
        $names = [];
        foreach ($this->localeRepository->findAll() as $locale) {
            $names[(string) $locale->getCode()] = $locale->getName($displayLocale) ?? (string) $locale->getCode();
        }

        $codes = $this->translationLocaleProvider->getDefinedLocalesCodes();
        $default = $this->translationLocaleProvider->getDefaultLocaleCode();
        usort($codes, static fn (string $a, string $b): int => [$a !== $default, $a] <=> [$b !== $default, $b]);

        return array_map(static fn (string $code): array => ['code' => $code, 'name' => $names[$code] ?? $code], $codes);
    }
}
