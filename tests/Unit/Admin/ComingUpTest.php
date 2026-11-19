<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\ComingUp;
use Setono\SyliusCMSPlugin\Admin\ComingUpEvent;
use Setono\SyliusCMSPlugin\Admin\ComingUpKind;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Symfony\Component\Clock\MockClock;

/**
 * See "Overview" in docs/spec/admin.md
 */
final class ComingUpTest extends TestCase
{
    use ProphecyTrait;

    private const NOW = '2026-11-20 10:00:00';

    /** @var ObjectProphecy<ScheduledPublishRepositoryInterface> */
    private ObjectProphecy $scheduledPublishes;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    protected function setUp(): void
    {
        $this->scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_merges_scheduled_publishes_and_the_starts_and_ends_of_date_windows_in_time_order(): void
    {
        $launch = self::page(from: '2026-11-20 13:00:00');
        $sale = self::page(from: '2026-11-20 12:30:00', until: '2026-11-20 15:00:00');
        $story = new Page();
        $this->given(
            scheduled: [self::scheduled($story, '2026-11-20 14:00:00')],
            starting: [$sale, $launch],
            ending: [$sale],
        );

        self::assertEquals([
            new ComingUpEvent(ComingUpKind::Starts, new \DateTimeImmutable('2026-11-20 12:30:00'), $sale),
            new ComingUpEvent(ComingUpKind::Starts, new \DateTimeImmutable('2026-11-20 13:00:00'), $launch),
            new ComingUpEvent(ComingUpKind::Publishes, new \DateTimeImmutable('2026-11-20 14:00:00'), $story),
            new ComingUpEvent(ComingUpKind::Ends, new \DateTimeImmutable('2026-11-20 15:00:00'), $sale),
        ], $this->comingUp(10)->events());
    }

    /**
     * Each source gives its first ones, so the first ones of all of them are among them
     *
     * @test
     */
    public function it_keeps_the_first_events_up_to_its_limit_and_asks_each_source_for_no_more(): void
    {
        $first = self::page(from: '2026-11-20 11:00:00');
        $second = self::page(until: '2026-11-20 12:00:00');
        $this->given(
            scheduled: [self::scheduled(new Page(), '2026-11-20 13:00:00'), self::scheduled(new Page(), '2026-11-20 14:00:00')],
            starting: [$first],
            ending: [$second],
            limit: 2,
        );

        self::assertEquals([
            new ComingUpEvent(ComingUpKind::Starts, new \DateTimeImmutable('2026-11-20 11:00:00'), $first),
            new ComingUpEvent(ComingUpKind::Ends, new \DateTimeImmutable('2026-11-20 12:00:00'), $second),
        ], $this->comingUp(2)->events());
    }

    /**
     * @test
     */
    public function it_lists_a_publish_before_a_start_and_a_start_before_an_end_at_the_same_time(): void
    {
        $page = self::page(from: '2026-11-20 12:00:00');
        $ending = self::page(until: '2026-11-20 12:00:00');
        $this->given(
            scheduled: [self::scheduled($page, '2026-11-20 12:00:00')],
            starting: [$page],
            ending: [$ending],
        );

        self::assertSame(
            [ComingUpKind::Publishes, ComingUpKind::Starts, ComingUpKind::Ends],
            array_map(static fn (ComingUpEvent $event): ComingUpKind => $event->kind, $this->comingUp(10)->events()),
        );
    }

    /**
     * @test
     */
    public function it_has_nothing_coming_up_when_nothing_is_scheduled_and_no_date_window_starts_or_ends(): void
    {
        $this->given(scheduled: [], starting: [], ending: []);

        self::assertSame([], $this->comingUp(10)->events());
    }

    /**
     * @param list<ScheduledPublishInterface> $scheduled
     * @param list<EntryInterface> $starting
     * @param list<EntryInterface> $ending
     */
    private function given(array $scheduled, array $starting, array $ending, int $limit = 10): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        // Without the entries of the content types switched off, whose overview pages answer 404
        $this->scheduledPublishes->findComingUp($now, $limit, ['store_location'])->willReturn($scheduled);
        $this->entries->findStartingAfter($now, $limit, ['store_location'])->willReturn($starting);
        $this->entries->findEndingAfter($now, $limit, ['store_location'])->willReturn($ending);
    }

    private function comingUp(int $limit): ComingUp
    {
        $contentTypes = $this->prophesize(ContentTypeRegistryInterface::class);
        $contentTypes->disabledCodes()->willReturn(['store_location']);

        return new ComingUp($this->scheduledPublishes->reveal(), $this->entries->reveal(), $contentTypes->reveal(), new MockClock(self::NOW), $limit);
    }

    private static function page(?string $from = null, ?string $until = null): Page
    {
        $page = new Page();
        $page->setDateWindow(new DateWindow(null === $from ? null : new \DateTimeImmutable($from), null === $until ? null : new \DateTimeImmutable($until)));

        return $page;
    }

    private static function scheduled(EntryInterface $entry, string $at): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($entry);
        $scheduledPublish->schedule(new \DateTimeImmutable($at), 'Ada');

        return $scheduledPublish;
    }
}
