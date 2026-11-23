<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * The one time zone scheduled publishes are entered and shown in, in the builder as on the overview page, whatever the
 * editor's browser uses: PHP's default, which the admin shows its other times in through Twig's date filter. The time
 * is stored in UTC. Everything that reads or shows a scheduled publish's time goes through it. See "Scheduled
 * publishes" in docs/spec/publishing.md
 *
 * @internal
 */
final class SchedulingTimeZone
{
    /**
     * Its name, such as Europe/Copenhagen, which labels the times
     */
    public function name(): string
    {
        return date_default_timezone_get();
    }

    /**
     * A time as the admin labels a scheduled publish's, in the zone, such as 2026-11-27 09:00
     */
    public function format(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone($this->name()))->format('Y-m-d H:i');
    }

    /**
     * A datetime-local input's value, such as 2026-11-27T09:00, as a time in the zone, or null when it isn't one
     */
    public function parse(string $value): ?\DateTimeImmutable
    {
        $at = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone($this->name()));

        return false === $at ? null : $at;
    }
}
