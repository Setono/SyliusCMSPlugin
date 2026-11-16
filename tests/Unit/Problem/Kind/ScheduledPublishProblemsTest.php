<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\ScheduledPublishFailed;
use Setono\SyliusCMSPlugin\Problem\Kind\ScheduledPublishOverdue;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The problems of scheduled publishes: overdue and failed, both errors fixed in the Publishing section of the entry's
 * overview page
 */
final class ScheduledPublishProblemsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ScheduledPublishRepositoryInterface> */
    private ObjectProphecy $scheduledPublishes;

    private Page $page;

    private string $timezone;

    protected function setUp(): void
    {
        $this->page = new Page();
        $this->scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn(null);

        // The admin shows times in PHP's default time zone
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    /**
     * @test
     */
    public function a_scheduled_publish_that_has_not_run_five_minutes_after_its_time_is_an_overdue_error(): void
    {
        $this->scheduledPublish('2026-11-16 11:55:00');

        self::assertEquals([new Problem(
            'scheduled_publish_overdue',
            Severity::Error,
            $this->page,
            new TranslatableMessage('setono_sylius_cms.problem.scheduled_publish_overdue', ['%at%' => '2026-11-16 12:55']),
            Fix::inPublishing($this->page),
        )], [...$this->overdue('2026-11-16 12:00:00')]);
    }

    /**
     * @test
     */
    public function a_scheduled_publish_that_waits_is_not_overdue_until_five_minutes_after_its_time(): void
    {
        $this->scheduledPublish('2026-11-16 11:55:01');

        self::assertSame([], [...$this->overdue('2026-11-16 12:00:00')]);
    }

    /**
     * The lists and the Content badge find it with a query condition, as nothing is written when it becomes overdue
     *
     * @test
     */
    public function an_overdue_error_is_found_in_queries_as_of_now_as_the_time_it_becomes_overdue_writes_nothing(): void
    {
        $kind = new ScheduledPublishOverdue($this->scheduledPublishes->reveal(), new MockClock());
        $queryBuilder = new QueryBuilder($this->prophesize(EntityManagerInterface::class)->reveal());

        $condition = $kind->getCondition($queryBuilder, 'o', new \DateTimeImmutable('2026-11-16 13:00:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame(Severity::Error, $kind->getSeverity());
        self::assertSame(sprintf(
            'EXISTS (SELECT cms_problem_overdue.id FROM %s cms_problem_overdue WHERE cms_problem_overdue.entry = o AND cms_problem_overdue.failureReason IS NULL AND cms_problem_overdue.publishAt <= :cms_problem_overdue_before)',
            ScheduledPublishInterface::class,
        ), $condition);
        $before = $queryBuilder->getParameter('cms_problem_overdue_before');
        self::assertNotNull($before);
        // The column holds UTC
        $value = $before->getValue();
        self::assertInstanceOf(\DateTimeImmutable::class, $value);
        self::assertEquals(new \DateTimeImmutable('2026-11-16 11:55:00', new \DateTimeZone('UTC')), $value);
        self::assertSame('UTC', $value->getTimezone()->getName());
        self::assertSame(Types::DATETIME_IMMUTABLE, $before->getType());
    }

    /**
     * @test
     */
    public function an_entry_without_a_scheduled_publish_has_neither_problem(): void
    {
        self::assertSame([], [...$this->overdue('2026-11-16 12:00:00')]);
        self::assertSame([], [...$this->failed()]);
    }

    /**
     * @test
     */
    public function a_failed_scheduled_publish_is_an_error_with_its_reason_and_not_overdue(): void
    {
        $this->scheduledPublish('2026-11-16 11:00:00')->fail('Not on a Sunday');

        self::assertEquals([new Problem(
            'scheduled_publish_failed',
            Severity::Error,
            $this->page,
            new TranslatableMessage('setono_sylius_cms.problem.scheduled_publish_failed', ['%at%' => '2026-11-16 12:00', '%reason%' => 'Not on a Sunday']),
            Fix::inPublishing($this->page),
        )], [...$this->failed()]);
        self::assertSame([], [...$this->overdue('2026-11-16 12:00:00')]);
    }

    /**
     * @test
     */
    public function a_scheduled_publish_that_waits_has_not_failed(): void
    {
        $this->scheduledPublish('2026-11-16 11:00:00');

        self::assertSame([], [...$this->failed()]);
    }

    /**
     * @return iterable<Problem>
     */
    private function overdue(string $now): iterable
    {
        return (new ScheduledPublishOverdue($this->scheduledPublishes->reveal(), new MockClock($now, 'UTC')))->findIn($this->page);
    }

    /**
     * @return iterable<Problem>
     */
    private function failed(): iterable
    {
        return (new ScheduledPublishFailed($this->scheduledPublishes->reveal()))->findIn($this->page);
    }

    private function scheduledPublish(string $at): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($this->page);
        $scheduledPublish->schedule(new \DateTimeImmutable($at, new \DateTimeZone('UTC')), 'Jane Roe');
        $this->scheduledPublishes->findOneByEntry($this->page)->willReturn($scheduledPublish);

        return $scheduledPublish;
    }
}
