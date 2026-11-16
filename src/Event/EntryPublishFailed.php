<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after a scheduled publish was stopped when it ran, by a validation error or a BeforeEntryPublish listener's
 * veto, and the transaction that flagged the entry as failed commits. It isn't retried, and the draft is unlocked, so
 * projects listen to it to tell someone
 */
final readonly class EntryPublishFailed
{
    /**
     * @param string $reason why it was stopped, as the entry's overview page shows it
     */
    public function __construct(
        public string $uuid,
        public string $reason,
    ) {
    }
}
