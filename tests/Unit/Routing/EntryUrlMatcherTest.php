<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Routing\EntryUrlMatcher;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class EntryUrlMatcherTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<EntryUrlLookupInterface> */
    private ObjectProphecy $lookup;

    /** @var ObjectProphecy<ChannelInterface> */
    private ObjectProphecy $channel;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class);
        $this->channel->getCode()->willReturn('WEB');

        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($this->channel->reveal());

        $this->lookup = $this->prophesize(EntryUrlLookupInterface::class);
    }

    /**
     * @test
     */
    public function it_matches_a_url_an_entry_claims_in_the_channel_and_locale(): void
    {
        $match = new EntryUrlMatch('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page');
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->willReturn($match);

        $matcher = $this->matcher();

        self::assertTrue($matcher->matches(['_locale' => 'en_US', 'path' => 'about-us']));
        self::assertSame($match, $matcher->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_does_not_match_a_url_no_entry_claims(): void
    {
        $this->lookup->lookup(Argument::cetera())->willReturn(null);

        self::assertFalse($this->matcher()->matches(['_locale' => 'en_US', 'path' => 'nothing']));
    }

    /**
     * @test
     */
    public function it_does_not_match_without_a_locale_or_a_path(): void
    {
        $this->lookup->lookup(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->matcher()->matches(['path' => 'about-us']));
        self::assertFalse($this->matcher()->matches(['_locale' => 'en_US']));
        self::assertFalse($this->matcher()->matches(['_locale' => ['en_US'], 'path' => 'about-us']));
    }

    /**
     * @test
     */
    public function it_does_not_match_without_a_channel(): void
    {
        $this->channelContext->getChannel()->willThrow(new ChannelNotFoundException());
        $this->lookup->lookup(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->matcher()->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_does_not_match_with_a_channel_that_is_not_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());
        $this->lookup->lookup(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->matcher()->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_looks_each_url_up_once_until_it_is_reset(): void
    {
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->willReturn(null)->shouldBeCalledTimes(2);
        $this->lookup->lookup($this->channel->reveal(), 'da_DK', 'about-us')->willReturn(null)->shouldBeCalledOnce();
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'contact')->willReturn(null)->shouldBeCalledOnce();

        $matcher = $this->matcher();
        $matcher->match('en_US', 'about-us');
        $matcher->match('en_US', 'about-us');
        $matcher->match('da_DK', 'about-us');
        $matcher->match('en_US', 'contact');

        $matcher->reset();
        $matcher->match('en_US', 'about-us');
    }

    /**
     * @test
     */
    public function it_looks_a_url_up_again_in_another_channel(): void
    {
        $other = $this->prophesize(ChannelInterface::class);
        $other->getCode()->willReturn('B2B');

        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->willReturn(null)->shouldBeCalledOnce();
        $this->lookup->lookup($other->reveal(), 'en_US', 'about-us')->willReturn(null)->shouldBeCalledOnce();

        $matcher = $this->matcher();
        $matcher->match('en_US', 'about-us');

        $this->channelContext->getChannel()->willReturn($other->reveal());
        $matcher->match('en_US', 'about-us');
        $matcher->match('en_US', 'about-us');
    }

    private function matcher(): EntryUrlMatcher
    {
        return new EntryUrlMatcher($this->channelContext->reveal(), $this->lookup->reveal());
    }
}
