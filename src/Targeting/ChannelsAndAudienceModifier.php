<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactoryInterface;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactoryInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

final readonly class ChannelsAndAudienceModifier implements ChannelsAndAudienceModifierInterface
{
    public function __construct(
        private EntryChannelFactoryInterface $channelFactory,
        private EntryAudienceGroupFactoryInterface $audienceGroupFactory,
    ) {
    }

    public function setChannels(EntryInterface $entry, iterable $codes): void
    {
        $entry->setChannelCodes($codes, $this->channelFactory);
    }

    public function setAudience(EntryInterface $entry, Audience $audience): void
    {
        $entry->setAudience($audience, $this->audienceGroupFactory);
    }
}
