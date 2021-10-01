<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class ChannelEligibilityChecker implements EligibilityCheckerInterface
{
    private ChannelContextInterface $channelContext;

    public function __construct(ChannelContextInterface $channelContext)
    {
        $this->channelContext = $channelContext;
    }

    public function isEligible(PageInterface $page): bool
    {
        $currentChannel = $this->channelContext->getChannel();

        foreach ($page->getChannels() as $channel) {
            if ($channel->getCode() === $currentChannel->getCode()) {
                return true;
            }
        }

        return false;
    }
}
