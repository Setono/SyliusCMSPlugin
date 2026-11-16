<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;

final class EntryStatusTest extends TestCase
{
    /**
     * @test
     */
    public function an_entry_is_never_published_until_it_is_published(): void
    {
        $page = new Page();
        self::assertSame('never_published', EntryStatus::of($page, false));

        $page->publish(new \DateTimeImmutable());
        self::assertSame('published', EntryStatus::of($page, false));
    }

    /**
     * @test
     */
    public function it_says_a_published_entry_with_a_draft_has_unpublished_changes(): void
    {
        $page = new Page();
        $page->publish(new \DateTimeImmutable());

        self::assertSame('unpublished_changes', EntryStatus::of($page, true));
    }

    /**
     * @test
     */
    public function it_says_a_scheduled_publish_publishes_at_a_time_until_it_is_overdue_five_minutes_after(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC')), 'Jane Roe');

        self::assertSame('publishes_at_a_time', EntryStatus::ofScheduledPublish($scheduledPublish, new \DateTimeImmutable('2026-11-20 08:04:59', new \DateTimeZone('UTC'))));
        self::assertSame('scheduled_publish_overdue', EntryStatus::ofScheduledPublish($scheduledPublish, new \DateTimeImmutable('2026-11-20 08:05:00', new \DateTimeZone('UTC'))));
    }

    /**
     * @test
     */
    public function it_says_failed_for_a_scheduled_publish_stopped_when_it_ran(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC')), 'Jane Roe');
        $scheduledPublish->fail('Not on a Sunday');

        self::assertSame('scheduled_publish_failed', EntryStatus::ofScheduledPublish($scheduledPublish, new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC'))));
    }
}
