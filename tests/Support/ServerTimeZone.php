<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

/**
 * Runs a test with PHP's default time zone, which the admin shows and reads times in, set to another than the machine's,
 * and sets it back afterwards. The kernel runs in the test's own process, so it's the server's time zone. Set it before
 * the kernel boots, as Twig keeps the time zone it first shows a date in
 */
trait ServerTimeZone
{
    private ?string $timeZoneBefore = null;

    protected function useServerTimeZone(string $timeZone): void
    {
        $this->timeZoneBefore ??= date_default_timezone_get();
        date_default_timezone_set($timeZone);
    }

    /**
     * @after
     */
    public function restoreServerTimeZone(): void
    {
        if (null !== $this->timeZoneBefore) {
            date_default_timezone_set($this->timeZoneBefore);
            $this->timeZoneBefore = null;
        }
    }
}
