<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Cache;

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
 * The caching cookbook's invalidation (docs/cookbook/caching.md): every item the CMS cache holds carries one tag, which
 * it invalidates whenever what shoppers see changes
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
