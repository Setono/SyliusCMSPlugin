<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Time;

/**
 * A length of time as an ISO 8601 duration, such as P1D, the way the bundle config gives media.grace_period and
 * bestsellers.window
 *
 * @internal
 */
final readonly class Duration
{
    private function __construct(public \DateInterval $interval)
    {
    }

    /**
     * @throws \InvalidArgumentException when it isn't an ISO 8601 duration
     */
    public static function fromIso8601(string $duration): self
    {
        try {
            return new self(new \DateInterval($duration));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('"%s" isn\'t an ISO 8601 duration, such as P1D', $duration), previous: $e);
        }
    }

    /**
     * The moment the duration after $from
     */
    public function after(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return $from->add($this->interval);
    }

    /**
     * The moment the duration before $until
     */
    public function before(\DateTimeImmutable $until): \DateTimeImmutable
    {
        return $until->sub($this->interval);
    }
}
