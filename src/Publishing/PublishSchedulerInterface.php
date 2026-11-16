<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Schedules publishes of entries and cancels them. An entry has at most one scheduled publish, which locks its draft,
 * or a never-published entry's rows, while it waits. See "Scheduled publishes" in docs/spec/publishing.md
 */
interface PublishSchedulerInterface
{
    /**
     * Schedules a publish of the entry's working copy for a time in the future, in place of the scheduled publish it
     * has. Validation and BeforeEntryPublish run first, so problems show at once, and they run again when it runs.
     * It sends the delayed message that runs it, and EntryPublishScheduled is dispatched
     *
     * @throws ScheduledPublishInThePastException when the time isn't in the future
     * @throws InvalidEntryException when validation finds errors in the entry's working copy
     * @throws PublishVetoedException when a BeforeEntryPublish listener vetoes the publish
     */
    public function schedule(EntryInterface $entry, \DateTimeImmutable $at): void;

    /**
     * Cancels the entry's scheduled publish, which unlocks its draft, and dispatches EntryPublishCancelled. A failed one
     * is cleared without the event. An entry without one is left as it is
     */
    public function cancel(EntryInterface $entry): void;

    /**
     * Flags the entry's scheduled publish as failed with the reason, when running it was stopped, which unlocks its
     * draft, and dispatches EntryPublishFailed. It isn't retried. It does nothing when the entry no longer has a scheduled
     * publish with the token waiting
     *
     * @param string $token the token of the scheduled publish that was stopped
     * @param string $reason why it was stopped, as the entry's overview page shows it
     */
    public function fail(EntryInterface $entry, string $token, string $reason): void;
}
