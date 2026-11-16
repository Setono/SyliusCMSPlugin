<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemSummaryProvider;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ProblemSummaryProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<ProblemFinderInterface> */
    private ObjectProphecy $finder;

    private ArrayAdapter $cache;

    private Page $withProblems;

    private Page $clean;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->finder = $this->prophesize(ProblemFinderInterface::class);
        $this->cache = new ArrayAdapter();

        $this->withProblems = new Page();
        $this->clean = new Page();
        $this->finder->findByEntry($this->withProblems)->willReturn([self::problem(Severity::Error), self::problem(Severity::Warning), self::problem(Severity::Warning)]);
        $this->finder->findByEntry($this->clean)->willReturn([]);
    }

    /**
     * @test
     */
    public function it_counts_every_entrys_problems_once_and_keeps_the_count_for_the_next_requests(): void
    {
        $this->entries->iterateAll()->willReturn([$this->withProblems, $this->clean])->shouldBeCalledOnce();
        $provider = $this->provider();

        $summary = $provider->getSummary();

        self::assertSame([$this->withProblems->getUuid()], $summary->entryUuids());
        self::assertEquals(new ProblemCount(1, 2), $summary->of($this->withProblems));
        self::assertEquals(new ProblemCount(), $summary->of($this->clean));
        self::assertSame($summary, $provider->getSummary());

        // The next request reads it from the cache
        $provider->reset();
        self::assertEquals($summary, $provider->getSummary());
        self::assertEquals($summary, $this->provider()->getSummary());
    }

    /**
     * @test
     */
    public function it_counts_again_once_the_summary_is_cleared(): void
    {
        $this->entries->iterateAll()->willReturn([$this->withProblems])->shouldBeCalledTimes(2);
        $provider = $this->provider();
        $provider->getSummary();
        $other = $this->provider();
        $other->getSummary();

        $provider->clear();

        self::assertSame([$this->withProblems->getUuid()], $other->getSummary()->entryUuids(), 'Another request in progress keeps what it read');
        $this->finder->findByEntry($this->withProblems)->willReturn([]);
        self::assertSame([], $provider->getSummary()->entryUuids());
    }

    /**
     * @test
     */
    public function it_leaves_out_an_entry_whose_stored_content_cannot_be_read(): void
    {
        $unreadableTree = new Page();
        $unreadableDraft = new Page();
        $this->entries->iterateAll()->willReturn([$unreadableTree, $this->withProblems, $unreadableDraft]);
        $this->finder->findByEntry($unreadableTree)->willThrow(new InvalidStoredTreeException('Not a tree'));
        $this->finder->findByEntry($unreadableDraft)->willThrow(new InvalidEntryFormatException('Not an entry'));

        self::assertSame([$this->withProblems->getUuid()], $this->provider()->getSummary()->entryUuids());
    }

    private function provider(): ProblemSummaryProvider
    {
        return new ProblemSummaryProvider($this->entries->reveal(), $this->finder->reveal(), $this->cache);
    }

    private static function problem(Severity $severity): Problem
    {
        return new Problem('kind', $severity, new Page(), new AnyMessage());
    }
}
