<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The entry_audience_group resource's factory: it creates the rows of the configured model, so a project that
 * overrides the model gets rows of its own class. The model implements EntryAudienceGroupInterface, usually by
 * extending EntryAudienceGroup, whose Doctrine mapping it inherits. Change an entry's audience through
 * ChannelsAndAudienceModifierInterface
 *
 * @extends FactoryInterface<EntryAudienceGroupInterface>
 */
interface EntryAudienceGroupFactoryInterface extends FactoryInterface
{
    /**
     * The row that puts the customer group value $code in the groups of $entry's audience
     */
    public function createForEntry(EntryInterface $entry, string $code): EntryAudienceGroupInterface;
}
