<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

/**
 * The channels whose homepage is a CMS page, so they don't render Sylius's own homepage nor the slots on it, such as
 * store_homepage, which the admin warns about. A channel counts as soon as a page is its homepage, published or not.
 * See "Rendering the homepage" in docs/spec/urls-and-seo.md
 *
 * @internal
 */
final readonly class CMSHomepageChannels
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private HomepageRepositoryInterface $homepages,
        private ChannelRepositoryInterface $channelRepository,
    ) {
    }

    /**
     * The names of those of these channels, or of every channel, whose homepage is a CMS page, in code order. A deleted
     * channel is named by its code
     *
     * @param list<string>|null $channelCodes null for every channel
     *
     * @return list<string>
     */
    public function names(?array $channelCodes = null): array
    {
        $channelCodes ??= array_map(static fn (ChannelInterface $channel): string => (string) $channel->getCode(), $this->channelRepository->findAll());
        if ([] === $channelCodes) {
            return [];
        }

        sort($channelCodes);
        $codes = array_map(static fn (HomepageInterface $homepage): string => $homepage->getChannelCode(), $this->homepages->findByChannelCodes($channelCodes));
        if ([] === $codes) {
            return [];
        }

        sort($codes);
        $names = [];
        foreach ($this->channelRepository->findBy(['code' => $codes]) as $channel) {
            $names[(string) $channel->getCode()] = $channel->getName() ?? (string) $channel->getCode();
        }

        return array_map(static fn (string $code): string => $names[$code] ?? $code, $codes);
    }
}
