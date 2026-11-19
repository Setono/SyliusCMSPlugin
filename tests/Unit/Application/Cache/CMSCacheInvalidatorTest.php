<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Application\Cache;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Event\StorefrontChangedInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CMSCacheInvalidator;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The caching cookbook's invalidation, docs/cookbook/caching.md
 */
final class CMSCacheInvalidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_invalidates_everything_the_cms_cache_holds_when_the_storefront_changes(): void
    {
        $cache = $this->prophesize(TagAwareCacheInterface::class);
        $cache->invalidateTags(['setono_sylius_cms'])->willReturn(true)->shouldBeCalledOnce();

        (new CMSCacheInvalidator($cache->reveal()))->invalidate(new PlacementsChanged(['0193a5be-0000-7000-8000-000000000001'], ['shop.footer']));
    }

    /**
     * Symfony dispatches an event by its class, not by the interfaces it implements, so the invalidator names each one.
     * A storefront change the plugin adds later fails this until the cookbook names it too
     *
     * @test
     */
    public function it_listens_to_every_event_that_changes_what_shoppers_see(): void
    {
        $events = CMSCacheInvalidator::getSubscribedEvents();

        self::assertEqualsCanonicalizing(self::storefrontChanges(), array_keys($events));
        foreach ($events as $method) {
            self::assertSame('invalidate', $method);
        }
    }

    /**
     * The event classes of the plugin that implement StorefrontChangedInterface
     *
     * @return list<class-string>
     */
    private static function storefrontChanges(): array
    {
        $classes = [];
        foreach ((array) glob(dirname(__DIR__, 4) . '/src/Event/*.php') as $file) {
            $class = 'Setono\\SyliusCMSPlugin\\Event\\' . basename((string) $file, '.php');
            if (!class_exists($class)) {
                continue;
            }

            if (is_subclass_of($class, StorefrontChangedInterface::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
