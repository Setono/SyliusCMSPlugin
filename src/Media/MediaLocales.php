<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * The store's locales, which media items have alt text in and which an image misses alt text in: the locales of the
 * store's channels, every channel's, not every locale Sylius defines. Sylius's default locale comes first, then the
 * others by code, as the media library's screen and the builder's media picker list them. See "Metadata" in
 * docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaLocales
{
    /**
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private ChannelLocales $channelLocales,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private RepositoryInterface $localeRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = $this->channelLocales->all();
        $default = $this->translationLocaleProvider->getDefaultLocaleCode();
        usort($codes, static fn (string $a, string $b): int => [$a !== $default, $a] <=> [$b !== $default, $b]);

        return $codes;
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

        return array_map(static fn (string $code): array => ['code' => $code, 'name' => $names[$code] ?? $code], $this->codes());
    }
}
