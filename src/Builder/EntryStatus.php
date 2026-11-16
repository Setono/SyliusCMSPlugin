<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;

/**
 * The entry states the admin and the builder show. See "Entry states" in docs/spec/publishing.md. An entry's state is
 * the state of its draft, of(), and, when it has a scheduled publish, the state of that, ofScheduledPublish()
 */
final class EntryStatus
{
    public const NEVER_PUBLISHED = 'never_published';

    public const PUBLISHED = 'published';

    public const UNPUBLISHED_CHANGES = 'unpublished_changes';

    /** A scheduled publish is waiting, which locks the draft, or a never-published entry's rows */
    public const PUBLISHES_AT_A_TIME = 'publishes_at_a_time';

    /** The scheduled publish's time has passed and it hasn't run */
    public const SCHEDULED_PUBLISH_OVERDUE = 'scheduled_publish_overdue';

    /** A scheduled publish was stopped when it ran */
    public const SCHEDULED_PUBLISH_FAILED = 'scheduled_publish_failed';

    /**
     * The state of the draft: never published, published, or unpublished changes
     */
    public static function of(EntryInterface $entry, bool $hasDraft): string
    {
        if (!$entry->isPublished()) {
            return self::NEVER_PUBLISHED;
        }

        return $hasDraft ? self::UNPUBLISHED_CHANGES : self::PUBLISHED;
    }

    /**
     * The state of the entry's scheduled publish: publishes at a time, overdue or failed, or null when it has none
     */
    public static function ofScheduledPublish(?ScheduledPublishInterface $scheduledPublish, \DateTimeImmutable $now): ?string
    {
        return match (true) {
            null === $scheduledPublish => null,
            $scheduledPublish->isFailed() => self::SCHEDULED_PUBLISH_FAILED,
            $scheduledPublish->isOverdue($now) => self::SCHEDULED_PUBLISH_OVERDUE,
            default => self::PUBLISHES_AT_A_TIME,
        };
    }
}
