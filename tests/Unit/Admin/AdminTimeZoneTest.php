<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Admin\AdminTimeZone;
use Setono\SyliusCMSPlugin\Tests\Support\ServerTimeZone;

/**
 * The admin and the builder enter and show times in PHP's default time zone, whatever the editor's browser uses. See
 * "Dates and times" in docs/spec/builder.md
 */
final class AdminTimeZoneTest extends TestCase
{
    use ServerTimeZone;

    /**
     * @test
     */
    public function it_is_phps_default_time_zone(): void
    {
        $this->useServerTimeZone('America/New_York');

        self::assertSame('America/New_York', (new AdminTimeZone())->name());
    }

    /**
     * @test
     */
    public function it_shows_a_time_in_the_zone(): void
    {
        $this->useServerTimeZone('Europe/Copenhagen');

        self::assertSame('2026-11-27 10:00', (new AdminTimeZone())->format(new \DateTimeImmutable('2026-11-27T09:00:00Z')));
    }

    /**
     * @test
     */
    public function it_reads_a_datetime_local_inputs_value_in_the_zone(): void
    {
        $this->useServerTimeZone('Europe/Copenhagen');
        $timeZone = new AdminTimeZone();

        self::assertEquals(new \DateTimeImmutable('2026-11-27T08:00:00Z'), $timeZone->parse('2026-11-27T09:00'));
        self::assertSame('Europe/Copenhagen', $timeZone->parse('2026-11-27T09:00')?->getTimezone()->getName());
        self::assertNull($timeZone->parse('next Friday'));
        self::assertNull($timeZone->parse(''));
    }
}
