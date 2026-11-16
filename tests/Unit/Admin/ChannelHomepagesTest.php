<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\ChannelHomepage;
use Setono\SyliusCMSPlugin\Admin\ChannelHomepages;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;

/**
 * "Homepage of" per channel on a page's overview page, with the page that's each channel's homepage now.
 * See "Entry overview page" in docs/spec/admin.md
 */
final class ChannelHomepagesTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    /** @var ObjectProphecy<HomepageRepositoryInterface> */
    private ObjectProphecy $homepages;

    protected function setUp(): void
    {
        $this->channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels->findBy([], ['id' => 'ASC'])->willReturn([self::channel('WEB', 'Web store'), self::channel('B2B', 'Wholesale'), self::channel('MOBILE', null)]);
        $this->homepages = $this->prophesize(HomepageRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_lists_each_channel_with_its_homepage_now_in_the_order_the_store_lists_them(): void
    {
        $page = new Page();
        Homepages::modifier()->setHomepageOf($page, ['WEB']);
        $sale = new Page();
        Homepages::modifier()->setHomepageOf($sale, ['B2B']);
        $this->homepages->findByChannelCodes(['WEB', 'B2B', 'MOBILE'])->willReturn([...$sale->getHomepages(), ...$page->getHomepages()]);

        self::assertEquals([
            new ChannelHomepage('WEB', 'Web store', $page, true),
            new ChannelHomepage('B2B', 'Wholesale', $sale, false),
            new ChannelHomepage('MOBILE', 'MOBILE', null, false),
        ], $this->channelHomepages()->of($page));
    }

    /**
     * A channel deleted since the page became its homepage is listed by its code
     *
     * @test
     */
    public function it_lists_a_deleted_channel_the_page_is_still_the_homepage_of(): void
    {
        $page = new Page();
        Homepages::modifier()->setHomepageOf($page, ['WEB', 'GONE']);
        $this->homepages->findByChannelCodes(['WEB', 'B2B', 'MOBILE', 'GONE'])->willReturn([...$page->getHomepages()]);

        self::assertEquals([
            new ChannelHomepage('WEB', 'Web store', $page, true),
            new ChannelHomepage('B2B', 'Wholesale', null, false),
            new ChannelHomepage('MOBILE', 'MOBILE', null, false),
            new ChannelHomepage('GONE', 'GONE', $page, true),
        ], $this->channelHomepages()->of($page));
    }

    /**
     * Only a page can be a channel's homepage
     *
     * @test
     */
    public function an_entry_other_than_a_page_has_none(): void
    {
        self::assertSame([], $this->channelHomepages()->of(new Announcement()));

        $this->homepages->findByChannelCodes(Argument::any())->shouldNotHaveBeenCalled();
    }

    private function channelHomepages(): ChannelHomepages
    {
        return new ChannelHomepages($this->channels->reveal(), $this->homepages->reveal());
    }

    private static function channel(string $code, ?string $name): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}
