<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

/**
 * The span from an optional start to an optional end in which an entry shows. "Show from" is inclusive and
 * "Show until" exclusive, so a window that ends at 2026-12-01T00:00:00Z shows until the last second of 30 November.
 * Its ends are kept in UTC, to the second, as the entry's columns hold them. See "Date window" in docs/spec/publishing.md
 */
final readonly class DateWindow
{
    public ?\DateTimeImmutable $from;

    public ?\DateTimeImmutable $until;

    /**
     * @throws \InvalidArgumentException when "Show until" doesn't come after "Show from"
     */
    public function __construct(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $until = null)
    {
        $this->from = self::normalize($from);
        $this->until = self::normalize($until);

        if (null !== $this->from && null !== $this->until && $this->until <= $this->from) {
            throw new \InvalidArgumentException(sprintf(
                '"Show until" (%s) must come after "Show from" (%s)',
                $this->until->format('Y-m-d\TH:i:s\Z'),
                $this->from->format('Y-m-d\TH:i:s\Z'),
            ));
        }
    }

    /**
     * Whether $at is inside the window. The visible-entries query filters the same way in SQL
     */
    public function contains(\DateTimeImmutable $at): bool
    {
        return (null === $this->from || $this->from <= $at) && (null === $this->until || $at < $this->until);
    }

    public function equals(self $other): bool
    {
        return self::same($this->from, $other->from) && self::same($this->until, $other->until);
    }

    private static function same(?\DateTimeImmutable $a, ?\DateTimeImmutable $b): bool
    {
        return $a?->getTimestamp() === $b?->getTimestamp();
    }

    private static function normalize(?\DateTimeImmutable $at): ?\DateTimeImmutable
    {
        if (null === $at) {
            return null;
        }

        $utc = $at->setTimezone(new \DateTimeZone('UTC'));

        return $utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), (int) $utc->format('s'));
    }
}
