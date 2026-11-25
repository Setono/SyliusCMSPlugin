<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * The one time zone the admin and the builder enter and show times in, whatever the editor's browser uses: PHP's
 * default, which the admin shows its other times in through Twig's date filter. It covers scheduled publishes, on the
 * overview page and in the builder, and every date and time the builder reads or shows, which the server gives it:
 * Date and time fields, the entry's and conditions' date windows and the preview's Date. Times are stored in UTC.
 * See "Dates and times" in docs/spec/builder.md and "Scheduled publishes" in docs/spec/publishing.md
 *
 * @internal
 */
final class AdminTimeZone
{
    /**
     * Its name, such as Europe/Copenhagen, which labels the times
     */
    public function name(): string
    {
        return date_default_timezone_get();
    }

    /**
     * A time as the admin labels it, in the zone, such as 2026-11-27 09:00
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
