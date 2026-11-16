<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

interface EntryPublisherInterface
{
    /**
     * Makes the entry's content the content shoppers see. It ends the entry's scheduled publish, if it has one
     *
     * @throws InvalidEntryException when validation finds errors in the entry's working copy
     * @throws PublishVetoedException when a BeforeEntryPublish listener vetoes the publish
     */
    public function publish(EntryInterface $entry): void;

    /**
     * Runs the entry's scheduled publish, as the Messenger worker does when its time comes: a publish whose revision
     * records it as scheduled, by who scheduled it, and whose EntryPublished says it was scheduled
     *
     * @param string $token the token of the scheduled publish the message is for
     *
     * @return bool false when the entry has no scheduled publish with the token waiting, because it was cancelled,
     *              replaced, published already or stopped, and nothing was done
     *
     * @throws InvalidEntryException when validation finds errors in the entry's working copy, and nothing was published
     * @throws PublishVetoedException when a BeforeEntryPublish listener vetoes the publish, and nothing was published
     */
    public function publishScheduled(EntryInterface $entry, string $token): bool;
}
