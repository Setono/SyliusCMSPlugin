<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactoryInterface;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactoryInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
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
        $entry = self::entry($entry);
        $codes = [...$codes];

        $kept = [];
        foreach ($entry->getChannels()->toArray() as $channel) {
            if (in_array($channel->getCode(), $codes, true)) {
                $kept[] = $channel->getCode();
            } else {
                $entry->removeChannel($channel);
            }
        }

        foreach (array_unique(array_diff($codes, $kept)) as $code) {
            $entry->addChannel($this->channelFactory->createForEntry($entry, $code));
        }
    }

    /**
     * The mode and the groups change together, and an Audience is always consistent, so the entry never shows to
     * everyone with groups left over
     */
    public function setAudience(EntryInterface $entry, Audience $audience): void
    {
        $entry = self::entry($entry);

        $kept = [];
        foreach ($entry->getAudienceGroups()->toArray() as $group) {
            if (in_array($group->getCode(), $audience->groups, true)) {
                $kept[] = $group->getCode();
            } else {
                $entry->removeAudienceGroup($group);
            }
        }

        foreach (array_diff($audience->groups, $kept) as $code) {
            $entry->addAudienceGroup($this->audienceGroupFactory->createForEntry($entry, $code));
        }

        $entry->setAudienceMode($audience->mode);
    }

    /**
     * The rows refer to the shipped Entry, which every content type extends
     */
    private static function entry(EntryInterface $entry): Entry
    {
        if (!$entry instanceof Entry) {
            throw new \LogicException(sprintf('The entry %s must extend %s, which its channel and audience group rows refer to', get_debug_type($entry), Entry::class));
        }

        return $entry;
    }
}
