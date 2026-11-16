<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprintInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdater;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

final class ProblemCountUpdaterTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<WorkingCopyProviderInterface> */
    private ObjectProphecy $workingCopies;

    /** @var ObjectProphecy<ProblemCountsQueryInterface> */
    private ObjectProphecy $query;

    /** @var ObjectProphecy<ProblemCountFingerprintInterface> */
    private ObjectProphecy $fingerprint;

    /** @var list<array{array<int, ProblemCount>, string, bool}> the arguments of each store() */
    private array $stored = [];

    /** @var array<int, list<Problem>> the problems of each working copy, by object id */
    private array $problems = [];

    protected function setUp(): void
    {
        $this->workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $this->query = $this->prophesize(ProblemCountsQueryInterface::class);
        $this->fingerprint = $this->prophesize(ProblemCountFingerprintInterface::class);
        $this->fingerprint->get()->willReturn('f1');

        $stored = &$this->stored;
        $this->query->store(Argument::cetera())->will(static function (array $arguments) use (&$stored): void {
            /** @var array{array<int, ProblemCount>, string, bool} $arguments */
            $stored[] = [$arguments[0], $arguments[1], $arguments[2] ?? false];
        });
    }

    /**
     * @test
     */
    public function it_counts_the_errors_and_warnings_of_each_entrys_working_copy_and_stores_them_by_the_entrys_id_with_the_fingerprint(): void
    {
        $withProblems = $this->entry(7, [Severity::Error, Severity::Warning, Severity::Error]);
        $clean = $this->entry(9, []);

        $this->updater()->update([$withProblems, $clean]);

        self::assertEquals([[[7 => new ProblemCount(2, 1), 9 => new ProblemCount()], 'f1', false]], $this->stored);
    }

    /**
     * @test
     */
    public function it_counts_content_it_cant_read_as_no_problems_as_that_fails_where_its_opened(): void
    {
        $brokenTree = $this->unreadable(3, new InvalidStoredTreeException('Not a block tree'));
        $brokenDraft = $this->unreadable(4, new InvalidEntryFormatException('Not an entry'));

        $this->updater()->update([$brokenTree, $brokenDraft]);

        self::assertEquals([[[3 => new ProblemCount(), 4 => new ProblemCount()], 'f1', false]], $this->stored);
    }

    /**
     * @test
     */
    public function it_stops_at_a_batch_of_stale_entries_it_cant_count_rather_than_reading_it_again(): void
    {
        $this->query->findStale('f1', 100, 0)->willReturn([new Page()])->shouldBeCalledOnce();

        $this->updater()->updateStale();

        self::assertSame([], $this->stored);
    }

    /**
     * The working copy of a never-published entry is the entry itself, upgraded where it's read, and the entry is
     * counted while Doctrine manages it, so it counts a copy, which a later flush never writes
     *
     * @test
     */
    public function it_counts_a_copy_of_the_entry_so_reading_its_working_copy_changes_nothing_a_flush_would_write(): void
    {
        $page = new Page();
        (new \ReflectionProperty(Page::class, 'id'))->setValue($page, 5);
        $page->setBlockTree(new StoredTree(null, [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a', 'heading', 1)]));
        $this->workingCopies->getWorkingCopy(Argument::type(Page::class))->will(static function (array $arguments): Page {
            /** @var Page $entry */
            $entry = $arguments[0];
            // As an upgrade does
            $entry->setBlockTree(new StoredTree(null, []));

            return $entry;
        });

        $this->updater()->update([$page]);

        self::assertCount(1, $page->getBlockTree()->blocks);
        self::assertEquals([[[5 => new ProblemCount()], 'f1', false]], $this->stored);
    }

    /**
     * @test
     */
    public function it_counts_no_entry_that_isnt_saved_yet_and_stores_nothing_without_entries(): void
    {
        $this->updater()->update([new Page()]);
        $this->updater()->update([]);

        self::assertSame([], $this->stored);
    }

    /**
     * @test
     */
    public function it_counts_again_the_entries_counted_with_other_code_or_never_batch_by_batch_without_overwriting_what_a_write_counted_meanwhile(): void
    {
        $first = $this->entry(3, [Severity::Warning]);
        $second = $this->entry(8, []);
        $third = $this->entry(12, [Severity::Error]);
        $this->query->findStale('f1', 100, 0)->willReturn([$first, $second]);
        $this->query->findStale('f1', 100, 8)->willReturn([$third]);
        $this->query->findStale('f1', 100, 12)->willReturn([]);

        $this->updater()->updateStale();

        self::assertEquals([
            [[3 => new ProblemCount(0, 1), 8 => new ProblemCount()], 'f1', true],
            [[12 => new ProblemCount(1, 0)], 'f1', true],
        ], $this->stored);
    }

    /**
     * @test
     */
    public function it_counts_nothing_again_when_every_entry_was_counted_with_the_code(): void
    {
        $this->query->findStale('f1', 100, 0)->willReturn([])->shouldBeCalledOnce();

        $this->updater()->updateStale();

        self::assertSame([], $this->stored);
    }

    /**
     * @param list<Severity> $severities the severities of the problems its working copy has, one kind each
     */
    private function entry(int $id, array $severities): EntryInterface
    {
        $entry = $this->prophesize(EntryInterface::class);
        $entry->getId()->willReturn($id);
        $entry = $entry->reveal();

        $workingCopy = $this->prophesize(EntryInterface::class)->reveal();
        $this->workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);
        $this->problems[spl_object_id($workingCopy)] = array_map(
            static fn (Severity $severity): Problem => new Problem('some', $severity, $workingCopy, new AnyMessage()),
            $severities,
        );

        return $entry;
    }

    private function unreadable(int $id, \Throwable $exception): EntryInterface
    {
        $entry = $this->prophesize(EntryInterface::class);
        $entry->getId()->willReturn($id);
        $entry = $entry->reveal();
        $this->workingCopies->getWorkingCopy($entry)->willThrow($exception);

        return $entry;
    }

    private function updater(): ProblemCountUpdater
    {
        $problems = &$this->problems;
        $kind = $this->prophesize(ProblemKindInterface::class);
        $kind->findIn(Argument::type(EntryInterface::class))->will(static function (array $arguments) use (&$problems): iterable {
            /** @var EntryInterface $workingCopy */
            $workingCopy = $arguments[0];

            // One kind finds the first problem, and the other the rest, through a generator
            return array_slice($problems[spl_object_id($workingCopy)] ?? [], 0, 1);
        });
        $otherKinds = $this->prophesize(ProblemKindInterface::class);
        $otherKinds->findIn(Argument::type(EntryInterface::class))->will(static function (array $arguments) use (&$problems): \Generator {
            /** @var EntryInterface $workingCopy */
            $workingCopy = $arguments[0];

            yield from array_slice($problems[spl_object_id($workingCopy)] ?? [], 1);
        });

        return new ProblemCountUpdater([$kind->reveal(), $otherKinds->reveal()], $this->workingCopies->reveal(), $this->query->reveal(), $this->fingerprint->reveal());
    }
}
