<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\NeedsAttention;
use Setono\SyliusCMSPlugin\Admin\ProblemGroup;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;

/**
 * The Overview's Needs attention: the problems of the entries that need attention, as the lists' filter finds them,
 * grouped by kind. See "Overview" in docs/spec/admin.md
 */
final class NeedsAttentionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<ProblemCountProviderInterface> */
    private ObjectProphecy $problemCounts;

    /** @var ObjectProphecy<ProblemFinderInterface> */
    private ObjectProphecy $problemFinder;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->problemCounts = $this->prophesize(ProblemCountProviderInterface::class);
        $this->problemFinder = $this->prophesize(ProblemFinderInterface::class);
        $this->problemFinder->findByEntry(Argument::any())->willReturn([]);
    }

    /**
     * @test
     */
    public function it_groups_the_problems_of_the_entries_that_need_attention_by_kind_with_the_errors_first(): void
    {
        $about = new Page();
        $contact = new Page();
        $this->needingAttention([$about, $contact]);
        $unknown = self::problem($about, 'unknown_block_type', Severity::Warning);
        $broken = self::problem($contact, 'broken_reference', Severity::Error);
        $this->problemFinder->findByEntry($about)->willReturn([$unknown]);
        $this->problemFinder->findByEntry($contact)->willReturn([$broken]);

        $found = $this->needsAttention()->find();

        self::assertEquals([
            new ProblemGroup('broken_reference', Severity::Error, [$broken]),
            new ProblemGroup('unknown_block_type', Severity::Warning, [$unknown]),
        ], $found->groups);
        self::assertSame([$about, $contact], $found->entries);
        self::assertSame(2, $found->total);
    }

    /**
     * So the Overview goes through no more entries than that, and links to all of them in All entries
     *
     * @test
     */
    public function it_finds_the_problems_of_the_first_entries_only_up_to_its_limit_and_counts_them_all(): void
    {
        $entries = array_map(static fn (): EntryInterface => new Page(), range(1, NeedsAttention::LIMIT + 1));
        $this->needingAttention($entries);

        $found = $this->needsAttention()->find();

        self::assertSame(array_slice($entries, 0, NeedsAttention::LIMIT), $found->entries);
        self::assertSame(NeedsAttention::LIMIT + 1, $found->total);
        $this->problemFinder->findByEntry(Argument::any())->shouldHaveBeenCalledTimes(NeedsAttention::LIMIT);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_no_entry_needs_attention(): void
    {
        $this->needingAttention([]);

        $found = $this->needsAttention()->find();

        self::assertSame([], $found->groups);
        self::assertSame([], $found->entries);
        self::assertSame(0, $found->total);
    }

    /**
     * @param list<EntryInterface> $entries the entries the lists' Needs attention filter finds, the one edited last first
     */
    private function needingAttention(array $entries): void
    {
        $this->entries->createNeedingAttentionPaginator($this->problemCounts->reveal())->willReturn(new Pagerfanta(new ArrayAdapter($entries)));
    }

    private function needsAttention(): NeedsAttention
    {
        return new NeedsAttention($this->entries->reveal(), $this->problemCounts->reveal(), $this->problemFinder->reveal());
    }

    private static function problem(EntryInterface $entry, string $kind, Severity $severity): Problem
    {
        return new Problem($kind, $severity, $entry, new AnyMessage());
    }
}
