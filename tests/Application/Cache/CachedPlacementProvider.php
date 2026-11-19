<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Cache;

use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The caching cookbook's cache of placements (docs/cookbook/caching.md): each channel's placements, read once and then
 * given from the cache until CMSCacheInvalidator invalidates it
 */
final readonly class CachedPlacementProvider implements PlacementProviderInterface
{
    public function __construct(
        private PlacementProviderInterface $decorated,
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function getPlacements(ChannelInterface $channel): array
    {
        /** @var list<PlacementData> $placements */
        $placements = $this->cache->get(
            sprintf('setono_sylius_cms.placements.%s', hash('xxh128', (string) $channel->getCode())),
            function (ItemInterface $item) use ($channel): array {
                $item->tag(CMSCacheInvalidator::TAG);

                return $this->decorated->getPlacements($channel);
            },
        );

        return $placements;
    }
}
