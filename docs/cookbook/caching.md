# Caching placements and URL lookups

v2.0 caches nothing it renders ([ADR 0021](../adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)). Every
shop page reads the channel's placements in one query when its first slot renders, an entry's URL is found with one
indexed query, and the records a block tree refers to load with one query per kind. Those are cheap, and a cache can't
serve a page that's out of date after a publish if there's no cache.

A busy shop that wants to save those queries can cache them. The plugin puts each behind one interface for that:

| Interface | Gives | Cache it? |
| --- | --- | --- |
| `Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface` | A channel's placements, as plain `PlacementData` values | Yes, [below](#caching-placements) |
| `Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface` | The entry a URL belongs to, as an `EntryUrlMatch` value | Yes, [below](#caching-url-lookups) |
| `Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface` | The products, taxons, entries and media items a block tree refers to | No, see [References](#references) |

A project caches one by decorating it with a tag-aware cache, and invalidates that cache when the plugin says the
storefront changed: every event that changes what shoppers see implements
`Setono\SyliusCMSPlugin\Event\StorefrontChangedInterface`.

The code on this page runs in the plugin's test application, in `tests/Application/Cache/`, wired as below by
`tests/Functional/Fixture/CMSCacheKernel.php`, and its tests are `tests/Unit/Application/Cache/` and
`tests/Functional/Shop/CMSCacheTest.php`.

## A tag-aware pool

Give the CMS a cache pool of its own, with tags:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            app.cms_cache:
                adapter: cache.app
                tags: true
```

`cache.app` is the filesystem by default, which suits a shop on one server, where the web server and the Messenger worker
share it. **With several servers, the pool must be one they all share**, such as Redis: an admin user's save, or a
scheduled publish on the worker, invalidates it for every server only then. Set `framework.cache.app` to a shared
adapter, or give this pool one:

```yaml
framework:
    cache:
        pools:
            app.cms_cache:
                adapter: cache.adapter.redis_tag_aware
                provider: '%env(REDIS_URL)%'
                tags: true
```

## Invalidating when the storefront changes

```php
// src/CMS/Cache/CMSCacheInvalidator.php
namespace App\CMS\Cache;

use Setono\SyliusCMSPlugin\Event\BestsellersComputed;
use Setono\SyliusCMSPlugin\Event\EntryDeleted;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Event\ImportFinished;
use Setono\SyliusCMSPlugin\Event\MediaItemDeleted;
use Setono\SyliusCMSPlugin\Event\MediaItemReplaced;
use Setono\SyliusCMSPlugin\Event\MediaItemSaved;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Event\StorefrontChangedInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Every item the CMS cache holds carries one tag, which it invalidates whenever what shoppers see changes
 */
final readonly class CMSCacheInvalidator implements EventSubscriberInterface
{
    /** The tag of every item the CMS cache holds */
    public const TAG = 'setono_sylius_cms';

    /**
     * How long, in seconds, the CMS cache keeps an item at most: what a change the invalidator never hears of can cost,
     * such as one written past the plugin's services, or one that commits while a request reads what it replaces
     */
    public const LIFETIME = 3600;

    public function __construct(private TagAwareCacheInterface $cache)
    {
    }

    /**
     * Every event that implements StorefrontChangedInterface. Symfony dispatches an event by its class, so each is named
     */
    public static function getSubscribedEvents(): array
    {
        return array_fill_keys([
            BestsellersComputed::class,
            EntryDeleted::class,
            EntryPublished::class,
            EntrySettingsChanged::class,
            ImportFinished::class,
            MediaItemDeleted::class,
            MediaItemReplaced::class,
            MediaItemSaved::class,
            PlacementsChanged::class,
        ], 'invalidate');
    }

    public function invalidate(StorefrontChangedInterface $event): void
    {
        $this->cache->invalidateTags([self::TAG]);
    }
}
```

- **The events are dispatched after the change commits**, in the request or on the worker that made it. An import holds
  its events until its one transaction commits, and forgets them when it rolls back.
- **Symfony dispatches an event by its class**, not by the interfaces it implements, so the subscriber names each one.
  When you upgrade the plugin, check [Events](../spec/extending.md#events) for new ones. The plugin's own test of this
  class fails when the plugin adds one, so this page keeps up.
- **Why one tag for everything, and not one per UUID.** Each event lists the UUIDs it touched in `getAffectedUuids()`,
  but a channel's placements and a URL lookup can change because of a record they don't hold yet: placing a new snippet
  changes the placements of a slot that didn't show it, and publishing an entry at another entry's old slug takes that
  URL over. Narrowing by UUID would keep those stale. Content changes are rare next to page views, so emptying the CMS's
  part of the cache on each one costs little. The tag keeps the rest of the pool.
- **Each item lives an hour at most**, which bounds what a missed invalidation costs: a change written past the plugin's
  services, such as by your own SQL, or one that commits while a request is reading what it replaces.

## Caching placements

```php
// src/CMS/Cache/CachedPlacementProvider.php
namespace App\CMS\Cache;

use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Each channel's placements, read once and then given from the cache until CMSCacheInvalidator invalidates it
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
                $item->tag(CMSCacheInvalidator::TAG)->expiresAfter(CMSCacheInvalidator::LIFETIME);

                return $this->decorated->getPlacements($channel);
            },
        );

        return $placements;
    }
}
```

The provider gives all of a channel's placements, of snippets visible or not, with their page scopes. Which of them show
on a page, for this shopper and at this time, is decided after it, on every request, so the cached list stays right
while snippets are published, disabled, or reach the end of their date window.

## Caching URL lookups

```php
// src/CMS/Cache/CachedEntryUrlLookup.php
namespace App\CMS\Cache;

use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Each URL an entry claims, by channel, locale and path, looked up once and then found in the cache until
 * CMSCacheInvalidator invalidates it. A URL no entry claims is looked up every time
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
            $item->tag(CMSCacheInvalidator::TAG)->expiresAfter(CMSCacheInvalidator::LIFETIME);

            $match = $this->decorated->lookup($channel, $localeCode, $path);
            $save = null !== $match;

            return $match;
        });

        return $match;
    }
}
```

- **A URL no entry claims isn't cached.** The entry route is matched after every other route, so every URL the shop
  doesn't know reaches the lookup, and bots ask for endless ones. An editor also creates an entry at a URL, and changes a
  never-published entry's slug, without a storefront event, and the builder's preview must find it at once.
- **The key is a hash**, because a path holds characters a cache key can't, such as `/`.
- **Whether the entry shows** is decided after the lookup, on every request, so a cached match never shows an entry that
  was disabled or left its date window.

## Wiring it

Add the services to `config/services.yaml`, after the `App\` resource, so these definitions win over the ones it
autowires:

```yaml
services:
    # ...

    App\CMS\Cache\CachedPlacementProvider:
        decorates: Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface
        arguments: ['@.inner', '@app.cms_cache']

    App\CMS\Cache\CachedEntryUrlLookup:
        decorates: Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface
        arguments: ['@.inner', '@app.cms_cache']

    App\CMS\Cache\CMSCacheInvalidator:
        arguments: ['@app.cms_cache']
        tags: ['kernel.event_subscriber']
```

The plugin's services all take the interfaces, so the decorators reach everything that reads placements and URLs: the
slots, the builder's snippet preview and the entry route's condition.

## Deploys

A deploy can change what the cached inputs mean without an event: a content type's URL prefix, a content type switched
off, or a plugin upgrade. Invalidate the CMS's part of the cache at every deploy, once the new code is in place:

```shell
bin/console cache:pool:invalidate-tags setono_sylius_cms --pool=app.cms_cache
```

On a filesystem pool inside the release's `var/cache`, a deploy to a new directory starts empty anyway, but the command
does no harm.

## References

`ReferenceLoaderInterface` is a seam a project can decorate, but this page doesn't cache it, and advises against it:

- **It gives Doctrine entities**, such as products with their translations, variants and images, which templates read
  lazily. Entities read back from a cache are detached from the entity manager, so those reads fail, and making them
  managed again costs the queries the cache saved.
- **Products and taxons change in Sylius without a CMS event**: a price, a stock level, a product disabled. A cache of
  them would show shoppers what the shop no longer sells.

They load with one query per kind of record, whatever the size of the page, so leave them to the database.
