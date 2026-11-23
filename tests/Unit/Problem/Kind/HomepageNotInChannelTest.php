<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\HomepageNotInChannel;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A page that's the homepage of a channel it isn't in, which shoppers there never see, so the store's own homepage
 * shows. See "Homepage" in docs/spec/urls-and-seo.md and "Problems" in docs/spec/admin.md
 */
final class HomepageNotInChannelTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> */
    private ObjectProphecy $channels;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
    }

    /**
     * A deleted channel is named by its code
     *
     * @test
     */
    public function it_warns_for_each_channel_the_page_is_the_homepage_of_without_being_in_it(): void
    {
        $page = $this->page(['B2B'], ['B2B', 'GONE', 'WEB']);
        $web = new Channel();
        $web->setCode('WEB');
        $web->setName('Web store');
        $this->channels->findBy(['code' => ['GONE', 'WEB']])->willReturn([$web]);

        self::assertEquals([
            self::problem($page, 'GONE'),
            self::problem($page, 'Web store'),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_the_page_is_in_every_channel_it_is_the_homepage_of(): void
    {
        $this->channels->findBy(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind()->findIn($this->page(['B2B', 'WEB'], ['WEB']))]);
        self::assertSame([], [...$this->kind()->findIn($this->page(['WEB'], []))]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_an_entry_that_is_not_a_page(): void
    {
        $this->channels->findBy(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind()->findIn(new Snippet())]);
    }

    private function kind(): HomepageNotInChannel
    {
        return new HomepageNotInChannel($this->channels->reveal());
    }

    /**
     * @param list<string> $channels
     * @param list<string> $homepageOf
     */
    private function page(array $channels, array $homepageOf): Page
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setChannels($page, $channels);
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, $homepageOf);

        return $page;
    }

    private static function problem(Page $page, string $channel): Problem
    {
        return new Problem(
            'homepage_not_in_channel',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.homepage_not_in_channel', ['%channel%' => $channel]),
            Fix::inBuilder($page),
        );
    }
}
