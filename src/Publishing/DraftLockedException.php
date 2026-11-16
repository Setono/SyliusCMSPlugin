<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;

/**
 * Thrown when something would change the working copy while a scheduled publish waits, which locks the draft, or a
 * never-published entry's rows, until it runs or is cancelled. See "Scheduled publishes" in docs/spec/publishing.md
 */
final class DraftLockedException extends \RuntimeException
{
    public function __construct(public readonly ScheduledPublishInterface $scheduledPublish)
    {
        parent::__construct(sprintf(
            'A publish scheduled for %s locks the draft until it runs or is cancelled',
            $scheduledPublish->getPublishAt()->format(\DATE_ATOM),
        ));
    }
}
