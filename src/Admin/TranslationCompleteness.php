<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

/**
 * The Overview's Translations: per channel and locale, how many of the channel's entries are complete in the locale,
 * counted in SQL from the locales stored with each entry. See "Overview" in docs/spec/admin.md and "Completeness and
 * outdated flags" in docs/spec/localization.md
 */
final readonly class TranslationCompleteness
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private CompletenessQueryInterface $query,
    ) {
    }

    /**
     * @return list<ChannelCompleteness> in the order the store lists its channels
     */
    public function byChannel(): array
    {
        $channels = $localesByChannel = [];
        foreach ($this->channelRepository->findBy([], ['id' => 'ASC']) as $channel) {
            $code = $channel->getCode();
            if (null !== $code) {
                $channels[$code] = $channel;
                $localesByChannel[$code] = ChannelLocales::of($channel);
            }
        }

        $byChannel = [];
        foreach ($this->query->count($localesByChannel) as $code => $count) {
            $locales = [];
            foreach ($count['complete'] as $localeCode => $complete) {
                $locales[] = new LocaleCompleteness($localeCode, $count['entries'], $complete);
            }

            $byChannel[] = new ChannelCompleteness($code, $channels[$code]->getName() ?? $code, $count['entries'], $locales);
        }

        return $byChannel;
    }
}
