<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

interface EntryPublisherInterface
{
    /**
     * Makes the entry's content the content shoppers see
     *
     * @throws InvalidEntryException when validation finds errors in the entry's working copy
     * @throws PublishVetoedException when a BeforeEntryPublish listener vetoes the publish
     */
    public function publish(EntryInterface $entry): void;
}
