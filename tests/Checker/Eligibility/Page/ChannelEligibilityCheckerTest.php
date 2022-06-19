<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Checker\Eligibility\Page\ChannelEligibilityChecker;
use Setono\SyliusCMSPlugin\Model\Page;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;

/**
 * @covers \Setono\SyliusCMSPlugin\Checker\Eligibility\Page\ChannelEligibilityChecker
 */
final class ChannelEligibilityCheckerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_eligible_when_current_channel_is_part_of_pages_channels(): void
    {
        $channel = new Channel();
        $channel->setCode('FASHION_WEB');

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        $channel1 = new Channel();
        $channel1->setCode('FASHION_WEB');

        $channel2 = new Channel();
        $channel2->setCode('GARDEN_WEB');

        $page = new Page();
        $page->addChannel($channel1);
        $page->addChannel($channel2);

        $checker = new ChannelEligibilityChecker($channelContext->reveal());
        self::assertTrue($checker->isEligible($page));
    }
}
