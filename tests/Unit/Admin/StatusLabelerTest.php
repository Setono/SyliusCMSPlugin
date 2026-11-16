<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Admin\StatusLabel;
use Setono\SyliusCMSPlugin\Admin\StatusLabeler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Symfony\Component\Clock\MockClock;

final class StatusLabelerTest extends TestCase
{
    private const NOW = '2026-11-16 12:00:00';

    /**
     * @test
     */
    public function it_labels_an_entry_that_was_never_published(): void
    {
        self::assertEquals([new StatusLabel(StatusLabel::NEVER_PUBLISHED)], $this->labelsOf(new Page(), false));
    }

    /**
     * @test
     */
    public function it_labels_a_published_entry_without_a_draft(): void
    {
        self::assertEquals([new StatusLabel(StatusLabel::PUBLISHED)], $this->labelsOf(self::published(), false));
    }

    /**
     * @test
     */
    public function it_labels_a_published_entry_with_a_draft_as_published_with_unpublished_changes(): void
    {
        self::assertEquals(
            [new StatusLabel(StatusLabel::PUBLISHED), new StatusLabel(StatusLabel::UNPUBLISHED_CHANGES)],
            $this->labelsOf(self::published(), true),
        );
    }

    /**
     * @test
     */
    public function it_labels_a_disabled_entry(): void
    {
        $page = self::published();
        $page->setEnabled(false);

        self::assertEquals(
            [new StatusLabel(StatusLabel::PUBLISHED), new StatusLabel(StatusLabel::DISABLED)],
            $this->labelsOf($page, false),
        );
    }

    /**
     * @test
     *
     * @dataProvider provideDateWindowsThatHaveNotEnded
     */
    public function it_labels_a_date_window_that_has_not_ended_with_its_ends(?string $from, ?string $until): void
    {
        $page = new Page();
        $page->setDateWindow(new DateWindow(self::utc($from), self::utc($until)));

        self::assertEquals(
            [new StatusLabel(StatusLabel::NEVER_PUBLISHED), new StatusLabel(StatusLabel::DATE_WINDOW, ['from' => self::utc($from), 'until' => self::utc($until)])],
            $this->labelsOf($page, false),
        );
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function provideDateWindowsThatHaveNotEnded(): iterable
    {
        yield 'starts later' => ['2026-12-01 00:00:00', null];
        yield 'started and ends later' => ['2026-11-01 00:00:00', '2026-12-01 00:00:00'];
        yield 'ends a second from now' => [null, '2026-11-16 12:00:01'];
        yield 'started' => ['2026-11-16 12:00:00', null];
    }

    /**
     * @test
     *
     * @dataProvider provideEndedDateWindows
     */
    public function it_labels_a_date_window_that_has_ended_with_when_it_ended(?string $from, string $until): void
    {
        $page = self::published();
        $page->setDateWindow(new DateWindow(self::utc($from), self::utc($until)));

        self::assertEquals(
            [new StatusLabel(StatusLabel::PUBLISHED), new StatusLabel(StatusLabel::ENDED, ['until' => self::utc($until)])],
            $this->labelsOf($page, false),
        );
    }

    /**
     * "Show until" is exclusive, so a window ends at its "Show until"
     *
     * @return iterable<string, array{string|null, string}>
     */
    public static function provideEndedDateWindows(): iterable
    {
        yield 'ends now' => [null, self::NOW];
        yield 'ended' => ['2026-10-01 00:00:00', '2026-11-01 00:00:00'];
    }

    /**
     * @test
     */
    public function it_labels_an_audience_that_is_not_everyone_with_its_groups(): void
    {
        $only = new Page();
        $only->setAudience(Audience::only('wholesale', CustomerGroupValue::GUEST));

        $except = new Page();
        $except->setAudience(Audience::except('retail'));

        self::assertEquals(
            [new StatusLabel(StatusLabel::NEVER_PUBLISHED), new StatusLabel(StatusLabel::AUDIENCE, ['mode' => 'only', 'groups' => [CustomerGroupValue::GUEST, 'wholesale']])],
            $this->labelsOf($only, false),
        );
        self::assertEquals(
            [new StatusLabel(StatusLabel::NEVER_PUBLISHED), new StatusLabel(StatusLabel::AUDIENCE, ['mode' => 'except', 'groups' => ['retail']])],
            $this->labelsOf($except, false),
        );
    }

    /**
     * @test
     */
    public function it_lists_the_state_then_the_on_save_settings(): void
    {
        $page = self::published();
        $page->setEnabled(false);
        $page->setDateWindow(new DateWindow(null, self::utc('2026-11-01 00:00:00')));
        $page->setAudience(Audience::only('wholesale'));

        self::assertSame(
            [StatusLabel::PUBLISHED, StatusLabel::UNPUBLISHED_CHANGES, StatusLabel::DISABLED, StatusLabel::ENDED, StatusLabel::AUDIENCE],
            array_map(static fn (StatusLabel $label): string => $label->code, $this->labelsOf($page, true)),
        );
    }

    /**
     * @return list<StatusLabel>
     */
    private function labelsOf(Page $page, bool $hasDraft): array
    {
        return (new StatusLabeler(new MockClock(self::NOW, 'UTC')))->labelsOf($page, $hasDraft);
    }

    private static function published(): Page
    {
        $page = new Page();
        $page->publish(new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC')));

        return $page;
    }

    /**
     * @return ($at is null ? null : \DateTimeImmutable)
     */
    private static function utc(?string $at): ?\DateTimeImmutable
    {
        return null === $at ? null : new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
