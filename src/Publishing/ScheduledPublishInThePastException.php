<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * Thrown when a publish is scheduled for a time that isn't in the future
 */
final class ScheduledPublishInThePastException extends \InvalidArgumentException
{
    public function __construct(public readonly \DateTimeImmutable $at)
    {
        parent::__construct(sprintf('A publish can only be scheduled for a time in the future, not %s', $at->format(\DATE_ATOM)));
    }
}
