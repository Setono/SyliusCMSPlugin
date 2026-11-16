<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowKind;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;

final class DateWindowKindTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_matches_from_its_start_inclusive_until_its_end_exclusive(): void
    {
        $params = self::window('2026-11-27T00:00:00Z', '2026-12-01T00:00:00Z');

        self::assertFalse((new DateWindowKind())->matches($params, $this->context('2026-11-26T23:59:59Z')));
        self::assertTrue((new DateWindowKind())->matches($params, $this->context('2026-11-27T00:00:00Z')));
        self::assertTrue((new DateWindowKind())->matches($params, $this->context('2026-11-30T23:59:59Z')), 'It shows until the last second of 30 November');
        self::assertFalse((new DateWindowKind())->matches($params, $this->context('2026-12-01T00:00:00Z')));
    }

    /**
     * @test
     */
    public function it_takes_either_end_as_optional(): void
    {
        self::assertTrue((new DateWindowKind())->matches(self::window(null, null), $this->context('2026-11-16T12:00:00Z')));

        self::assertFalse((new DateWindowKind())->matches(self::window('2026-11-27T00:00:00Z', null), $this->context('2026-11-26T23:59:59Z')));
        self::assertTrue((new DateWindowKind())->matches(self::window('2026-11-27T00:00:00Z', null), $this->context('2030-01-01T00:00:00Z')));

        self::assertTrue((new DateWindowKind())->matches(self::window(null, '2026-12-01T00:00:00Z'), $this->context('2020-01-01T00:00:00Z')));
        self::assertFalse((new DateWindowKind())->matches(self::window(null, '2026-12-01T00:00:00Z'), $this->context('2026-12-01T00:00:00Z')));
    }

    /**
     * @test
     */
    public function it_compares_points_in_time_whatever_their_time_zone(): void
    {
        $params = self::window('2026-11-27T00:00:00Z', null);

        self::assertTrue((new DateWindowKind())->matches($params, $this->context('2026-11-27T01:00:00+01:00')));
        self::assertFalse((new DateWindowKind())->matches($params, $this->context('2026-11-27T00:59:59+01:00')));
    }

    /**
     * @test
     */
    public function it_changes_next_at_its_start_while_that_is_ahead_then_at_its_end(): void
    {
        $params = self::window('2026-11-27T00:00:00Z', '2026-12-01T00:00:00Z');
        $kind = new DateWindowKind();

        self::assertEquals(new \DateTimeImmutable('2026-11-27T00:00:00Z'), $kind->nextChange($params, new \DateTimeImmutable('2026-11-26T23:59:59Z')));
        self::assertEquals(new \DateTimeImmutable('2026-12-01T00:00:00Z'), $kind->nextChange($params, new \DateTimeImmutable('2026-11-27T00:00:00Z')), 'At its start it has changed already');
        self::assertEquals(new \DateTimeImmutable('2026-12-01T00:00:00Z'), $kind->nextChange($params, new \DateTimeImmutable('2026-11-30T23:59:59Z')));
        self::assertNull($kind->nextChange($params, new \DateTimeImmutable('2026-12-01T00:00:00Z')), 'It never changes again once it ended');
    }

    /**
     * @test
     */
    public function it_never_changes_at_an_open_end(): void
    {
        $kind = new DateWindowKind();

        self::assertNull($kind->nextChange(self::window(null, null), new \DateTimeImmutable('2026-11-16T12:00:00Z')));
        self::assertNull($kind->nextChange(self::window('2026-11-01T00:00:00Z', null), new \DateTimeImmutable('2026-11-16T12:00:00Z')));
        self::assertEquals(new \DateTimeImmutable('2026-12-01T00:00:00Z'), $kind->nextChange(self::window(null, '2026-12-01T00:00:00Z'), new \DateTimeImmutable('2026-11-16T12:00:00Z')));
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The date window condition takes %s, got %s', DateWindowParams::class, NoParams::class));

        (new DateWindowKind())->nextChange(new NoParams(), new \DateTimeImmutable());
    }

    private static function window(?string $from, ?string $until): DateWindowParams
    {
        $params = new DateWindowParams();
        $params->from = null === $from ? null : new \DateTimeImmutable($from);
        $params->until = null === $until ? null : new \DateTimeImmutable($until);

        return $params;
    }

    private function context(string $now): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable($now));
    }
}
