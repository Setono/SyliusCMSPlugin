<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProvider;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdaterInterface;
use Setono\SyliusCMSPlugin\Problem\TimeDependentProblemKindInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Symfony\Component\Clock\MockClock;

final class ProblemCountProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProblemCountsQueryInterface> */
    private ObjectProphecy $query;

    /** @var ObjectProphecy<ProblemCountUpdaterInterface> */
    private ObjectProphecy $updater;

    /** @var list<TimeDependentProblemKindInterface> */
    private array $kinds;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->query = $this->prophesize(ProblemCountsQueryInterface::class);
        $this->updater = $this->prophesize(ProblemCountUpdaterInterface::class);
        $kind = $this->prophesize(TimeDependentProblemKindInterface::class);
        $kind->getCondition(Argument::cetera())->willReturn('o.overdue = 1');
        $this->kinds = [$kind->reveal()];
        $this->clock = new MockClock('2026-11-16 12:00:00');
    }

    /**
     * @test
     */
    public function it_gives_each_entry_its_stored_count_once_the_stale_counts_are_updated(): void
    {
        $counted = new Page();
        $unsaved = new Page();
        $this->updater->updateStale()->shouldBeCalledOnce();
        $this->query->ofEntries([$counted, $unsaved], $this->kinds, $this->clock->now())->willReturn([$counted->getUuid() => new ProblemCount(1, 2)]);

        $counts = $this->provider()->getCounts((static fn () => yield from [$counted, $unsaved])());

        self::assertEquals([$counted->getUuid() => new ProblemCount(1, 2), $unsaved->getUuid() => new ProblemCount()], $counts);
    }

    /**
     * @test
     */
    public function it_gives_the_stores_errors_and_warnings_with_the_time_dependent_ones_as_of_now_once_the_stale_counts_are_updated(): void
    {
        $this->updater->updateStale()->shouldBeCalledOnce();
        $this->query->total($this->kinds, $this->clock->now())->willReturn(new ProblemCount(3, 4));

        self::assertEquals(new ProblemCount(3, 4), $this->provider()->getTotal());
    }

    /**
     * @test
     */
    public function its_needs_attention_condition_holds_for_the_entries_with_a_stored_error_or_warning_once_the_stale_counts_are_updated(): void
    {
        $this->updater->updateStale()->shouldBeCalledOnce();
        $queryBuilder = new QueryBuilder($this->prophesize(EntityManagerInterface::class)->reveal());
        $this->kinds = [];

        self::assertSame('o.problemErrors > 0 OR o.problemWarnings > 0', $this->provider()->getNeedsAttentionCondition($queryBuilder, 'o'));
        self::assertCount(0, $queryBuilder->getParameters());
    }

    /**
     * @test
     */
    public function its_needs_attention_condition_holds_too_for_the_entries_with_a_problem_of_a_time_dependent_kind_as_of_now(): void
    {
        $queryBuilder = new QueryBuilder($this->prophesize(EntityManagerInterface::class)->reveal());
        $overdue = $this->prophesize(TimeDependentProblemKindInterface::class);
        $overdue->getCondition($queryBuilder, 'o', $this->clock->now())->willReturn('EXISTS (SELECT s FROM S s WHERE s.entry = o) OR 1 = 0');
        $expiring = $this->prophesize(TimeDependentProblemKindInterface::class);
        $expiring->getCondition($queryBuilder, 'o', $this->clock->now())->willReturn('o.until < :now');
        $this->kinds = [$overdue->reveal(), $expiring->reveal()];

        self::assertSame(
            'o.problemErrors > 0 OR o.problemWarnings > 0 OR (EXISTS (SELECT s FROM S s WHERE s.entry = o) OR 1 = 0) OR (o.until < :now)',
            $this->provider()->getNeedsAttentionCondition($queryBuilder, 'o'),
        );
    }

    /**
     * @test
     */
    public function it_updates_the_stale_counts_once_a_request(): void
    {
        $page = new Page();
        $this->query->ofEntries([$page], $this->kinds, $this->clock->now())->willReturn([]);
        $provider = $this->provider();

        $this->updater->updateStale()->shouldBeCalledOnce();
        $provider->getCounts([$page]);
        $provider->getCounts([$page]);
        $this->updater->checkProphecyMethodsPredictions();

        $this->query->total($this->kinds, $this->clock->now())->willReturn(new ProblemCount());
        $provider->getTotal();
        $provider->getNeedsAttentionCondition(new QueryBuilder($this->prophesize(EntityManagerInterface::class)->reveal()), 'o');
        $this->updater->checkProphecyMethodsPredictions();

        $provider->reset();
        $this->updater->updateStale()->shouldBeCalledTimes(2);
        $provider->getCounts([$page]);
    }

    /**
     * @test
     */
    public function it_reads_nothing_for_no_entries(): void
    {
        $this->query->ofEntries(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], $this->provider()->getCounts([]));
    }

    private function provider(): ProblemCountProvider
    {
        return new ProblemCountProvider($this->query->reveal(), $this->updater->reveal(), $this->kinds, $this->clock);
    }
}
