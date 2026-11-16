<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Runs each URL through the route checker in each channel, on the channel's hostname. The store's channels are read
 * once, since every entry is in some of the same few. A channel code no channel has any more is skipped
 */
final class TakenUrlFinder implements TakenUrlFinderInterface, ResetInterface
{
    /** @var array<string, ChannelInterface>|null by code */
    private ?array $channels = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ContentTypeRegistryInterface $contentTypes,
        private readonly RouteCheckerInterface $routeChecker,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly Availability $availability,
    ) {
    }

    public function find(EntryInterface $entry, array $slugs, array $channelCodes): array
    {
        return $this->findIn($entry, $slugs, $channelCodes, false);
    }

    public function findLive(EntryInterface $entry): array
    {
        $channelCodes = $entry->getChannelCodes();
        if (!$entry->isPublished() || [] === $channelCodes) {
            return [];
        }

        $slugs = Slug::of($entry, fn (string $localeCode): bool => $this->availability->showsIn($entry, $localeCode));

        return $this->findIn($entry, $slugs, $channelCodes, true);
    }

    /**
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     * @param bool $shownOnly whether to check a URL only in the channels that have its locale, where it shows
     *
     * @return list<TakenUrl>
     */
    private function findIn(EntryInterface $entry, array $slugs, array $channelCodes, bool $shownOnly): array
    {
        $contentType = $this->contentTypes->getByEntry($entry);

        $channels = [] === $channelCodes ? [null] : array_intersect_key($this->channels(), array_flip($channelCodes));

        $taken = [];
        foreach ($channels as $channel) {
            foreach ($slugs as $localeCode => $slug) {
                $path = EntryPath::ofSlug($contentType, $slug);
                if (null === $path || ($shownOnly && !self::shows($channel, $localeCode))) {
                    continue;
                }

                $route = $this->routeChecker->takenBy($channel, $localeCode, $path);
                if (null !== $route) {
                    $taken[] = new TakenUrl($channel?->getCode(), $localeCode, $path, $route);
                }
            }
        }

        return $taken;
    }

    public function reset(): void
    {
        $this->channels = null;
    }

    /**
     * @return array<string, ChannelInterface> by code, in code order
     */
    private function channels(): array
    {
        if (null === $this->channels) {
            $this->channels = [];
            foreach ($this->channelRepository->findAll() as $channel) {
                if ($channel instanceof ChannelInterface && null !== $channel->getCode()) {
                    $this->channels[$channel->getCode()] = $channel;
                }
            }

            ksort($this->channels);
        }

        return $this->channels;
    }

    private static function shows(?ChannelInterface $channel, string $localeCode): bool
    {
        if (null === $channel) {
            return true;
        }

        foreach ($channel->getLocales() as $locale) {
            if ($locale->getCode() === $localeCode) {
                return true;
            }
        }

        return false;
    }
}
