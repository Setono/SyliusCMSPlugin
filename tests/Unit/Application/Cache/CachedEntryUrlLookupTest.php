<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Application\Cache;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CachedEntryUrlLookup;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CMSCacheInvalidator;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * The caching cookbook's cache of URL lookups, docs/cookbook/caching.md
 */
final class CachedEntryUrlLookupTest extends TestCase
{
    use ProphecyTrait;

    private const ABOUT_US = '0193a5be-0000-7000-8000-000000000001';

    private const BLOG_POST = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_looks_each_url_up_once_by_channel_locale_and_path_and_then_finds_it_in_the_cache(): void
    {
        $web = self::channel('WEB');
        $outlet = self::channel('OUTLET');
        $aboutUs = new EntryUrlMatch(self::ABOUT_US, 'page');
        $oldSlug = new EntryUrlMatch(self::ABOUT_US, 'page', 'about-us');
        $inDanish = new EntryUrlMatch(self::ABOUT_US, 'page', 'om-os');
        $inOutlet = new EntryUrlMatch(self::BLOG_POST, 'blog_post');
        $decorated = $this->prophesize(EntryUrlLookupInterface::class);
        $decorated->lookup($web, 'en_US', 'about-us')->willReturn($aboutUs)->shouldBeCalledOnce();
        $decorated->lookup($web, 'en_US', 'about')->willReturn($oldSlug)->shouldBeCalledOnce();
        $decorated->lookup($web, 'da_DK', 'about-us')->willReturn($inDanish)->shouldBeCalledOnce();
        $decorated->lookup($outlet, 'en_US', 'about-us')->willReturn($inOutlet)->shouldBeCalledOnce();

        $lookup = new CachedEntryUrlLookup($decorated->reveal(), new TagAwareAdapter(new ArrayAdapter()));

        foreach ([1, 2] as $time) {
            self::assertEquals($aboutUs, $lookup->lookup($web, 'en_US', 'about-us'));
            self::assertEquals($oldSlug, $lookup->lookup($web, 'en_US', 'about'));
            self::assertEquals($inDanish, $lookup->lookup($web, 'da_DK', 'about-us'));
            self::assertEquals($inOutlet, $lookup->lookup($outlet, 'en_US', 'about-us'));
        }
    }

    /**
     * Any path can be asked for, and an editor can create an entry at one at any time, without an event
     *
     * @test
     */
    public function it_looks_a_url_no_entry_claims_up_every_time(): void
    {
        $web = self::channel('WEB');
        $decorated = $this->prophesize(EntryUrlLookupInterface::class);
        $decorated->lookup($web, 'en_US', 'nowhere')->willReturn(null)->shouldBeCalledTimes(2);

        $lookup = new CachedEntryUrlLookup($decorated->reveal(), new TagAwareAdapter(new ArrayAdapter()));

        self::assertNull($lookup->lookup($web, 'en_US', 'nowhere'));
        self::assertNull($lookup->lookup($web, 'en_US', 'nowhere'));
    }

    /**
     * @test
     */
    public function it_looks_the_url_up_again_once_the_storefront_changed(): void
    {
        $web = self::channel('WEB');
        $before = new EntryUrlMatch(self::ABOUT_US, 'page', 'about-us');
        $after = new EntryUrlMatch(self::BLOG_POST, 'page');
        $decorated = $this->prophesize(EntryUrlLookupInterface::class);
        $decorated->lookup($web, 'en_US', 'about')->willReturn($before, $after)->shouldBeCalledTimes(2);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $lookup = new CachedEntryUrlLookup($decorated->reveal(), $cache);

        self::assertEquals($before, $lookup->lookup($web, 'en_US', 'about'));
        self::assertEquals($before, $lookup->lookup($web, 'en_US', 'about'));

        // Another entry is published at the old slug's URL, which it takes over
        (new CMSCacheInvalidator($cache))->invalidate(new EntryPublished(self::BLOG_POST, 'page', [], ['en_US' => 'about'], false));

        self::assertEquals($after, $lookup->lookup($web, 'en_US', 'about'));
        self::assertEquals($after, $lookup->lookup($web, 'en_US', 'about'));
    }

    private static function channel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
