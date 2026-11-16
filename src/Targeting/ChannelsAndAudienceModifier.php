<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactoryInterface;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactoryInterface;
use Setono\SyliusCMSPlugin\Model\CodedRows;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Works out which rows to add and which to remove, and keeps the rows of the codes that stay: Doctrine removes rows
 * after it inserts them, so a new row for a code that stays would clash with the old one's unique key
 */
final readonly class ChannelsAndAudienceModifier implements ChannelsAndAudienceModifierInterface
{
    public function __construct(
        private EntryChannelFactoryInterface $channelFactory,
        private EntryAudienceGroupFactoryInterface $audienceGroupFactory,
    ) {
    }

    public function setChannels(EntryInterface $entry, iterable $codes): void
    {
        CodedRows::sync(
            $entry->getChannels()->toArray(),
            [...$codes],
            static fn (EntryChannelInterface $channel): string => $channel->getCode(),
            $entry->removeChannel(...),
            fn (string $code) => $entry->addChannel($this->channelFactory->createForEntry($entry, $code)),
        );
    }

    /**
     * The mode and the groups change together, and an Audience is always consistent, so the entry never shows to
     * everyone with groups left over
     */
    public function setAudience(EntryInterface $entry, Audience $audience): void
    {
        CodedRows::sync(
            $entry->getAudienceGroups()->toArray(),
            $audience->groups,
            static fn (EntryAudienceGroupInterface $group): string => $group->getCode(),
            $entry->removeAudienceGroup(...),
            fn (string $code) => $entry->addAudienceGroup($this->audienceGroupFactory->createForEntry($entry, $code)),
        );

        $entry->setAudienceMode($audience->mode);
    }
}
