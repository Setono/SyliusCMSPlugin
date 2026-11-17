<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Time;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Time\Duration;

final class DurationTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_the_moments_the_duration_before_and_after_another(): void
    {
        $duration = Duration::fromIso8601('P30D');

        self::assertEquals(new \DateTimeImmutable('2026-10-18 12:00:00'), $duration->before(new \DateTimeImmutable('2026-11-17 12:00:00')));
        self::assertEquals(new \DateTimeImmutable('2026-12-17 12:00:00'), $duration->after(new \DateTimeImmutable('2026-11-17 12:00:00')));
    }

    /**
     * @test
     */
    public function it_refuses_what_is_not_an_iso_8601_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"30 days" isn\'t an ISO 8601 duration, such as P1D');

        Duration::fromIso8601('30 days');
    }
}
