<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Cache;

use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The caching cookbook's cache of URL lookups (docs/cookbook/caching.md): each URL an entry claims, by channel, locale
 * and path, looked up once and then found in the cache until CMSCacheInvalidator invalidates it. A URL no entry claims
 * is looked up every time: any path can be asked for, and an editor creates an entry at one without an event
 */
final readonly class CachedEntryUrlLookup implements EntryUrlLookupInterface
{
    public function __construct(
        private EntryUrlLookupInterface $decorated,
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function lookup(ChannelInterface $channel, string $localeCode, string $path): ?EntryUrlMatch
    {
        $key = sprintf('setono_sylius_cms.url.%s', hash('xxh128', implode("\n", [(string) $channel->getCode(), $localeCode, $path])));

        /** @var EntryUrlMatch|null $match */
        $match = $this->cache->get($key, function (ItemInterface $item, bool &$save) use ($channel, $localeCode, $path): ?EntryUrlMatch {
            $item->tag(CMSCacheInvalidator::TAG);

            $match = $this->decorated->lookup($channel, $localeCode, $path);
            $save = null !== $match;

            return $match;
        });

        return $match;
    }
}
