<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Targeting;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

/**
 * "Show from" is inclusive and "Show until" exclusive. See "Date window" in docs/spec/publishing.md
 */
final class DateWindowTest extends TestCase
{
    /**
     * @test
     */
    public function show_from_is_inclusive(): void
    {
        $window = new DateWindow(self::utc('2026-11-27 00:00:00'), null);

        self::assertFalse($window->contains(self::utc('2026-11-26 23:59:59')));
        self::assertTrue($window->contains(self::utc('2026-11-27 00:00:00')));
        self::assertTrue($window->contains(self::utc('2030-01-01 00:00:00')));
    }

    /**
     * @test
     */
    public function show_until_is_exclusive(): void
    {
        $window = new DateWindow(null, self::utc('2026-12-01 00:00:00'));

        self::assertTrue($window->contains(self::utc('2000-01-01 00:00:00')));
        self::assertTrue($window->contains(self::utc('2026-11-30 23:59:59')));
        self::assertFalse($window->contains(self::utc('2026-12-01 00:00:00')));
    }

    /**
     * @test
     */
    public function a_window_with_both_ends_contains_the_span_between_them(): void
    {
        $window = new DateWindow(self::utc('2026-11-27 00:00:00'), self::utc('2026-12-01 00:00:00'));

        self::assertFalse($window->contains(self::utc('2026-11-26 23:59:59')));
        self::assertTrue($window->contains(self::utc('2026-11-27 00:00:00')));
        self::assertTrue($window->contains(self::utc('2026-11-30 23:59:59')));
        self::assertFalse($window->contains(self::utc('2026-12-01 00:00:00')));
    }

    /**
     * @test
     */
    public function a_window_without_ends_contains_every_time(): void
    {
        $window = new DateWindow();

        self::assertNull($window->from);
        self::assertNull($window->until);
        self::assertTrue($window->contains(self::utc('1970-01-01 00:00:00')));
        self::assertTrue($window->contains(self::utc('2999-12-31 23:59:59')));
    }

    /**
     * @test
     */
    public function it_compares_instants_whatever_their_time_zones(): void
    {
        $window = new DateWindow(new \DateTimeImmutable('2026-11-27 01:00:00', new \DateTimeZone('Europe/Copenhagen')), null);

        self::assertFalse($window->contains(self::utc('2026-11-26 23:59:59')));
        self::assertTrue($window->contains(new \DateTimeImmutable('2026-11-27 01:00:00', new \DateTimeZone('Europe/Copenhagen'))));
    }

    /**
     * The ends are stored as columns in UTC, to the second
     *
     * @test
     */
    public function its_ends_are_in_utc_to_the_second(): void
    {
        $window = new DateWindow(
            new \DateTimeImmutable('2026-11-27 01:00:00.750', new \DateTimeZone('Europe/Copenhagen')),
            new \DateTimeImmutable('2026-12-01T00:00:00.5+00:00'),
        );

        self::assertSame('2026-11-27T00:00:00.000000+00:00', $window->from?->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-12-01T00:00:00.000000+00:00', $window->until?->format('Y-m-d\TH:i:s.uP'));
    }

    /**
     * @test
     */
    public function show_until_must_come_after_show_from(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Show until" (2026-11-27T00:00:00Z) must come after "Show from" (2026-11-27T00:00:00Z)');

        new DateWindow(self::utc('2026-11-27 00:00:00'), new \DateTimeImmutable('2026-11-27 01:00:00', new \DateTimeZone('Europe/Copenhagen')));
    }

    /**
     * @test
     */
    public function windows_are_equal_when_their_ends_are_the_same_instants(): void
    {
        $from = self::utc('2026-11-27 00:00:00');
        $until = self::utc('2026-12-01 00:00:00');

        self::assertTrue((new DateWindow($from, $until))->equals(new DateWindow(new \DateTimeImmutable('2026-11-27 01:00:00', new \DateTimeZone('Europe/Copenhagen')), $until)));
        self::assertTrue((new DateWindow())->equals(new DateWindow()));
        self::assertFalse((new DateWindow($from, $until))->equals(new DateWindow($from, null)));
        self::assertFalse((new DateWindow($from, $until))->equals(new DateWindow(null, $until)));
        self::assertFalse((new DateWindow($from, null))->equals(new DateWindow(self::utc('2026-11-27 00:00:01'), null)));
        self::assertFalse((new DateWindow(null, $until))->equals(new DateWindow(null, self::utc('2026-12-01 00:00:01'))));
    }

    private static function utc(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }
}
