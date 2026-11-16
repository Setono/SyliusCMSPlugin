<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Symfony\Component\Uid\Uuid;

final class ScheduledPublishTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_a_publish_of_an_entry_at_a_time_in_utc_by_an_editor(): void
    {
        $page = new Page();
        $scheduledPublish = new ScheduledPublish();
        self::assertNull($scheduledPublish->getId());
        self::assertNull($scheduledPublish->getScheduledBy());
        self::assertNull($scheduledPublish->getFailureReason());
        self::assertFalse($scheduledPublish->isFailed());

        $scheduledPublish->setEntry($page);
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 09:00:00', new \DateTimeZone('Europe/Copenhagen')), 'Jane Roe');

        self::assertSame($page, $scheduledPublish->getEntry());
        self::assertSame('2026-11-20T08:00:00+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));
        self::assertSame('Jane Roe', $scheduledPublish->getScheduledBy());
        self::assertTrue(Uuid::isValid($scheduledPublish->getToken()));
    }

    /**
     * @test
     */
    public function it_gets_a_new_token_each_time_it_is_scheduled_so_the_message_of_the_one_it_replaces_does_nothing(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00'), 'Jane Roe');
        $first = $scheduledPublish->getToken();

        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00'), null);

        self::assertNotSame($first, $scheduledPublish->getToken());
        self::assertTrue(Uuid::isValid($scheduledPublish->getToken()));
        self::assertNull($scheduledPublish->getScheduledBy());
    }

    /**
     * @test
     */
    public function it_is_flagged_as_failed_with_the_reason_until_it_is_scheduled_again(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00'), 'Jane Roe');

        $scheduledPublish->fail('Not on a Sunday');

        self::assertTrue($scheduledPublish->isFailed());
        self::assertSame('Not on a Sunday', $scheduledPublish->getFailureReason());

        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-21 08:00:00'), 'Jane Roe');

        self::assertFalse($scheduledPublish->isFailed());
        self::assertNull($scheduledPublish->getFailureReason());
    }

    /**
     * @test
     */
    public function it_is_overdue_five_minutes_after_its_time(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC')), 'Jane Roe');

        self::assertFalse($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-20 07:59:00', new \DateTimeZone('UTC'))));
        self::assertFalse($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC'))));
        self::assertFalse($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-20 08:04:59', new \DateTimeZone('UTC'))));
        self::assertTrue($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-20 08:05:00', new \DateTimeZone('UTC'))));
        // The same moment in another time zone
        self::assertTrue($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-20 09:05:00', new \DateTimeZone('Europe/Copenhagen'))));
        self::assertFalse($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-20 09:04:59', new \DateTimeZone('Europe/Copenhagen'))));
    }

    /**
     * @test
     */
    public function it_is_never_overdue_once_it_has_failed(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC')), 'Jane Roe');
        $scheduledPublish->fail('Not on a Sunday');

        self::assertFalse($scheduledPublish->isOverdue(new \DateTimeImmutable('2026-11-21 08:00:00', new \DateTimeZone('UTC'))));
    }

    /**
     * @test
     */
    public function it_reads_its_time_in_utc_whatever_time_zone_doctrine_hydrates_it_in(): void
    {
        $scheduledPublish = new ScheduledPublish();
        $publishAt = new \ReflectionProperty(ScheduledPublish::class, 'publishAt');
        $publishAt->setValue($scheduledPublish, new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame('2026-11-20T08:00:00+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_belongs_to_an_entry_of_a_content_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ScheduledPublish())->setEntry($this->prophesize(EntryInterface::class)->reveal());
    }
}
