<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\GracePeriod;

final class GracePeriodTest extends TestCase
{
    /**
     * @test
     */
    public function it_delays_by_one_day_by_default(): void
    {
        self::assertSame(86_400_000, (new GracePeriod())->delayInMilliseconds(new \DateTimeImmutable('2026-11-17 10:00:00')));
    }

    /**
     * @test
     */
    public function it_delays_by_an_iso_8601_duration(): void
    {
        self::assertSame(5_400_000, (new GracePeriod('PT1H30M'))->delayInMilliseconds(new \DateTimeImmutable('2026-11-17 10:00:00')));
        self::assertSame(7 * 86_400_000, (new GracePeriod('P1W'))->delayInMilliseconds(new \DateTimeImmutable('2026-11-17 10:00:00')));
    }

    /**
     * @test
     */
    public function it_counts_a_duration_in_months_from_when_it_starts(): void
    {
        self::assertSame(28 * 86_400_000, (new GracePeriod('P1M'))->delayInMilliseconds(new \DateTimeImmutable('2026-02-01 10:00:00')));
        self::assertSame(31 * 86_400_000, (new GracePeriod('P1M'))->delayInMilliseconds(new \DateTimeImmutable('2026-03-01 10:00:00')));
    }

    /**
     * @test
     */
    public function it_delays_by_nothing_for_a_duration_of_zero(): void
    {
        self::assertSame(0, (new GracePeriod('PT0S'))->delayInMilliseconds(new \DateTimeImmutable('2026-11-17 10:00:00')));
    }

    /**
     * @test
     */
    public function it_counts_in_utc_so_a_change_to_summer_time_doesnt_shorten_it(): void
    {
        self::assertSame(86_400_000, (new GracePeriod('P1D'))->delayInMilliseconds(new \DateTimeImmutable('2026-03-28 12:00:00', new \DateTimeZone('Europe/Copenhagen'))));
    }

    /**
     * @test
     */
    public function it_refuses_what_isnt_an_iso_8601_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"1 day" isn\'t an ISO 8601 duration, such as P1D');

        new GracePeriod('1 day');
    }
}
