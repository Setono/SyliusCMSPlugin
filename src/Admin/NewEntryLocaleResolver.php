<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * The locale a new entry's title and slug go into, which the builder then opens in: the admin user's locale, when one
 * of the entry's channels has it, and otherwise the default locale of the first of the entry's channels that has one,
 * or else the store's default locale. See "Create dialog" in docs/spec/admin.md
 */
final readonly class NewEntryLocaleResolver implements NewEntryLocaleResolverInterface
{
    /**
     * @param LocaleContextInterface $localeContext in the admin, the admin user's locale
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private LocaleContextInterface $localeContext,
        private ChannelRepositoryInterface $channelRepository,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
    ) {
    }

    public function resolve(EntryInterface $entry): string
    {
        $channels = [];
        foreach ($entry->getChannelCodes() as $code) {
            $channel = $this->channelRepository->findOneByCode($code);
            if ($channel instanceof CoreChannelInterface) {
                $channels[] = $channel;
            }
        }

        $adminLocale = $this->localeContext->getLocaleCode();
        foreach ($channels as $channel) {
            foreach ($channel->getLocales() as $locale) {
                if ($locale->getCode() === $adminLocale) {
                    return $adminLocale;
                }
            }
        }

        foreach ($channels as $channel) {
            $default = $channel->getDefaultLocale()?->getCode();
            if (null !== $default) {
                return $default;
            }
        }

        return $this->translationLocaleProvider->getDefaultLocaleCode();
    }
}
