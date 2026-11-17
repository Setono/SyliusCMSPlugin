<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * How long a replaced file and its variants are kept before they're deleted, media.grace_period, so pages cached before
 * the replace don't show broken images in the meantime. See "Replacing a file" in docs/spec/media.md
 */
final readonly class GracePeriod
{
    public const DEFAULT = 'P1D';

    public \DateInterval $duration;

    /**
     * @param string $duration an ISO 8601 duration, such as P1D
     *
     * @throws \InvalidArgumentException when it isn't one
     */
    public function __construct(string $duration = self::DEFAULT)
    {
        try {
            $this->duration = new \DateInterval($duration);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('"%s" isn\'t an ISO 8601 duration, such as P1D', $duration), previous: $e);
        }
    }

    /**
     * How long a message sent at $from is delayed, for Messenger's DelayStamp. A duration in months or years is counted
     * from $from, and every duration in UTC, so a day is always 24 hours
     */
    public function delayInMilliseconds(\DateTimeImmutable $from): int
    {
        $from = $from->setTimezone(new \DateTimeZone('UTC'));

        return ($from->add($this->duration)->getTimestamp() - $from->getTimestamp()) * 1000;
    }
}
