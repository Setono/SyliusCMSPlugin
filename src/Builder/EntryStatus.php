<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;

/**
 * The entry states the admin and the builder show. See "Entry states" in docs/spec/publishing.md. An entry's state is
 * the state of its draft, of(), and, when it has a scheduled publish, the state of that, ofScheduledPublish(). The admin's
 * status labels of these states have their value as their code
 */
enum EntryStatus: string
{
    case NeverPublished = 'never_published';
    case Published = 'published';
    case UnpublishedChanges = 'unpublished_changes';

    /** A scheduled publish is waiting, which locks the draft, or a never-published entry's rows */
    case PublishesAtATime = 'publishes_at_a_time';

    /** The scheduled publish's time has passed and it hasn't run */
    case ScheduledPublishOverdue = 'scheduled_publish_overdue';

    /** A scheduled publish was stopped when it ran */
    case ScheduledPublishFailed = 'scheduled_publish_failed';

    /**
     * The state of the draft: never published, published, or unpublished changes
     */
    public static function of(EntryInterface $entry, bool $hasDraft): self
    {
        if (!$entry->isPublished()) {
            return self::NeverPublished;
        }

        return $hasDraft ? self::UnpublishedChanges : self::Published;
    }

    /**
     * The state of the entry's scheduled publish: publishes at a time, overdue or failed
     */
    public static function ofScheduledPublish(ScheduledPublishInterface $scheduledPublish, \DateTimeImmutable $now): self
    {
        return match (true) {
            $scheduledPublish->isFailed() => self::ScheduledPublishFailed,
            $scheduledPublish->isOverdue($now) => self::ScheduledPublishOverdue,
            default => self::PublishesAtATime,
        };
    }
}
