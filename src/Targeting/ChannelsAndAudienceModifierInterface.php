<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Changes an entry's channels and audience. Everything that changes them goes through it: the entry factories, which
 * start a new entry in every channel, and saving the on-save settings. It creates their rows through the entry_channel
 * and entry_audience_group resources' factories, so a project that overrides those models gets rows of its own classes
 */
interface ChannelsAndAudienceModifierInterface
{
    /**
     * Puts the entry in exactly these channels
     *
     * @param iterable<string> $codes channel codes
     */
    public function setChannels(EntryInterface $entry, iterable $codes): void;

    public function setAudience(EntryInterface $entry, Audience $audience): void;
}
