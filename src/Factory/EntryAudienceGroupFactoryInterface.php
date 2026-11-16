<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The entry_audience_group resource's factory: it creates the rows of the configured model, so a project that
 * overrides the model gets rows of its own class. The model extends EntryAudienceGroup, whose Doctrine mapping it
 * inherits. Change an entry's audience through ChannelsAndAudienceModifierInterface
 *
 * @extends FactoryInterface<EntryAudienceGroup>
 */
interface EntryAudienceGroupFactoryInterface extends FactoryInterface
{
    /**
     * The row that puts the customer group value $code in the groups of $entry's audience
     */
    public function createForEntry(Entry $entry, string $code): EntryAudienceGroup;
}
