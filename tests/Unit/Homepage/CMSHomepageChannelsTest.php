<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Homepage;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Homepage\CMSHomepageChannels;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The channels whose homepage is a CMS page, so they don't render Sylius's own homepage. See "Rendering the homepage"
 * in docs/spec/urls-and-seo.md
 */
final class CMSHomepageChannelsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<HomepageRepositoryInterface> */
    private ObjectProphecy $homepages;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    protected function setUp(): void
    {
        $this->homepages = $this->prophesize(HomepageRepositoryInterface::class);
        $this->channels = $this->prophesize(ChannelRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_names_those_of_the_channels_given_whose_homepage_is_a_cms_page_in_code_order_and_a_deleted_one_by_its_code(): void
    {
        $this->homepages->findByChannelCodes(['B2B', 'GONE', 'WEB'])->willReturn([new Homepage(new Page(), 'WEB'), new Homepage(new Page(), 'GONE')]);
        $this->channels->findBy(['code' => ['GONE', 'WEB']])->willReturn([self::channel('WEB', 'Web store')]);

        self::assertSame(['GONE', 'Web store'], $this->channels()->names(['WEB', 'GONE', 'B2B']));
    }

    /**
     * @test
     */
    public function it_names_none_when_no_channel_given_has_a_cms_homepage(): void
    {
        $this->homepages->findByChannelCodes(['B2B'])->willReturn([]);
        $this->channels->findBy([])->shouldNotBeCalled();

        self::assertSame([], $this->channels()->names(['B2B']));
        self::assertSame([], $this->channels()->names([]));
    }

    /**
     * @test
     */
    public function it_names_those_of_every_channel_whose_homepage_is_a_cms_page(): void
    {
        $this->channels->findAll()->willReturn([self::channel('WEB', 'Web store'), self::channel('B2B', 'Wholesale')]);
        $this->homepages->findByChannelCodes(['B2B', 'WEB'])->willReturn([new Homepage(new Page(), 'B2B')]);
        $this->channels->findBy(['code' => ['B2B']])->willReturn([self::channel('B2B', 'Wholesale')]);

        self::assertSame(['Wholesale'], $this->channels()->names());
    }

    private function channels(): CMSHomepageChannels
    {
        return new CMSHomepageChannels($this->homepages->reveal(), $this->channels->reveal());
    }

    private static function channel(string $code, string $name): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}
