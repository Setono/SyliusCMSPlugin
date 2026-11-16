<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The entry_channel resource's factory: it creates the rows of the configured model, so a project that overrides the
 * model gets rows of its own class. The model extends EntryChannel, whose Doctrine mapping it inherits.
 * Change an entry's channels through ChannelsAndAudienceModifierInterface
 *
 * @extends FactoryInterface<EntryChannel>
 */
interface EntryChannelFactoryInterface extends FactoryInterface
{
    /**
     * The row that puts $entry in the channel with the code $code
     */
    public function createForEntry(Entry $entry, string $code): EntryChannel;
}
