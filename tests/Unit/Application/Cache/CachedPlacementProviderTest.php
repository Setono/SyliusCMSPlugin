<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Application\Cache;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CachedPlacementProvider;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CMSCacheInvalidator;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The caching cookbook's cache of placements, docs/cookbook/caching.md
 */
final class CachedPlacementProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_reads_each_channel_s_placements_once_and_then_gives_them_from_the_cache(): void
    {
        $web = self::channel('WEB');
        $outlet = self::channel('OUTLET');
        $webPlacements = [new PlacementData('0193a5be-0000-7000-8000-00000000000a', '0193a5be-0000-7000-8000-000000000001', 'shop.footer', [], 0)];
        $outletPlacements = [new PlacementData('0193a5be-0000-7000-8000-00000000000b', '0193a5be-0000-7000-8000-000000000002', 'shop.top', [], 0)];
        $decorated = $this->prophesize(PlacementProviderInterface::class);
        $decorated->getPlacements($web)->willReturn($webPlacements)->shouldBeCalledOnce();
        $decorated->getPlacements($outlet)->willReturn($outletPlacements)->shouldBeCalledOnce();

        $provider = new CachedPlacementProvider($decorated->reveal(), new TagAwareAdapter(new ArrayAdapter()));

        self::assertEquals($webPlacements, $provider->getPlacements($web));
        self::assertEquals($outletPlacements, $provider->getPlacements($outlet));
        self::assertEquals($webPlacements, $provider->getPlacements($web));
        self::assertEquals($outletPlacements, $provider->getPlacements($outlet));
    }

    /**
     * @test
     */
    public function it_reads_the_placements_again_once_the_storefront_changed(): void
    {
        $web = self::channel('WEB');
        $before = [new PlacementData('0193a5be-0000-7000-8000-00000000000a', '0193a5be-0000-7000-8000-000000000001', 'shop.footer', [], 0)];
        $after = [...$before, new PlacementData('0193a5be-0000-7000-8000-00000000000b', '0193a5be-0000-7000-8000-000000000002', 'shop.footer', [], 1)];
        $decorated = $this->prophesize(PlacementProviderInterface::class);
        $decorated->getPlacements($web)->willReturn($before, $after)->shouldBeCalledTimes(2);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $provider = new CachedPlacementProvider($decorated->reveal(), $cache);

        self::assertEquals($before, $provider->getPlacements($web));
        self::assertEquals($before, $provider->getPlacements($web));

        (new CMSCacheInvalidator($cache))->invalidate(new PlacementsChanged(['0193a5be-0000-7000-8000-000000000002'], ['shop.footer']));

        self::assertEquals($after, $provider->getPlacements($web));
        self::assertEquals($after, $provider->getPlacements($web));
    }

    /**
     * A change the invalidator never hears of, such as one written past the plugin's services, shows within the hour
     *
     * @test
     */
    public function it_keeps_a_channel_s_placements_for_an_hour_at_most(): void
    {
        $web = self::channel('WEB');
        $decorated = $this->prophesize(PlacementProviderInterface::class);
        $decorated->getPlacements($web)->willReturn([]);
        $item = $this->prophesize(ItemInterface::class);
        $item->tag(CMSCacheInvalidator::TAG)->willReturn($item->reveal())->shouldBeCalled();
        $item->expiresAfter(3600)->willReturn($item->reveal())->shouldBeCalled();
        $cache = $this->prophesize(TagAwareCacheInterface::class);
        $cache->get(Argument::type('string'), Argument::type('callable'))->will(static function (array $arguments) use ($item): mixed {
            $save = true;

            return $arguments[1]($item->reveal(), $save);
        });

        self::assertSame([], (new CachedPlacementProvider($decorated->reveal(), $cache->reveal()))->getPlacements($web));
    }

    private static function channel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
