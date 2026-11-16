<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifier;

/**
 * For unit tests: changes an entry's channels and audience with the shipped row models, as the container does when a
 * project overrides neither
 */
final class ChannelsAndAudience
{
    public static function modifier(): ChannelsAndAudienceModifier
    {
        return new ChannelsAndAudienceModifier(self::channelFactory(), self::audienceGroupFactory());
    }

    public static function channelFactory(): EntryChannelFactory
    {
        return new EntryChannelFactory(EntryChannel::class);
    }

    public static function audienceGroupFactory(): EntryAudienceGroupFactory
    {
        return new EntryAudienceGroupFactory(EntryAudienceGroup::class);
    }
}
