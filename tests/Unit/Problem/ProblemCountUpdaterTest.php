<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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

    /** @var list<array{array<int, ProblemCount>, string}> the arguments of each store() */
    private array $stored = [];

    /** @var array<int, list<Problem>> the problems of each working copy, by object id */
    private array $problems = [];

    /** @var array<int, EntryInterface> the working copy of each entry, by the entry's object id */
    private array $workingCopyOf = [];

    protected function setUp(): void
    {
        $this->workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $this->query = $this->prophesize(ProblemCountsQueryInterface::class);
        $this->fingerprint = $this->prophesize(ProblemCountFingerprintInterface::class);
        $this->fingerprint->get()->willReturn('f1');

        $stored = &$this->stored;
        $this->query->store(Argument::cetera())->will(static function (array $arguments) use (&$stored): void {
            /** @var array{array<int, ProblemCount>, string} $arguments */
            $stored[] = [$arguments[0], $arguments[1]];
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

        self::assertEquals([[[7 => new ProblemCount(2, 1), 9 => new ProblemCount()], 'f1']], $this->stored);
    }

    /**
     * @test
     */
    public function it_counts_content_it_cant_read_as_one_error(): void
    {
        $brokenTree = $this->unreadable(3, new InvalidStoredTreeException('Not a block tree'));
        $brokenDraft = $this->unreadable(4, new InvalidEntryFormatException('Not an entry'));

        $this->updater()->update([$brokenTree, $brokenDraft]);

        self::assertEquals([[[3 => new ProblemCount(1, 0), 4 => new ProblemCount(1, 0)], 'f1']], $this->stored);
    }

    /**
     * A project's kind that fails never fails the save that counts. The entry keeps the counts it had
     *
     * @test
     */
    public function it_logs_a_kind_that_fails_on_an_entry_and_leaves_that_entrys_counts_as_they_were(): void
    {
        $failing = $this->entry(3, [Severity::Error]);
        $clean = $this->entry(4, []);
        $kind = $this->prophesize(ProblemKindInterface::class);
        $failingCopy = $this->workingCopyOf($failing);
        $kind->findIn(Argument::any())->will(static function (array $arguments) use ($failingCopy): \Generator {
            if ($arguments[0] === $failingCopy) {
                throw new \RuntimeException('The API is down');
            }

            yield from [];
        });
        $logger = $this->prophesize(LoggerInterface::class);

        $this->updater([$kind->reveal()], $logger->reveal())->update([$failing, $clean]);

        self::assertEquals([[[4 => new ProblemCount()], 'f1']], $this->stored);
        $logger->error(Argument::containingString('failed on the entry'), Argument::withEntry('message', 'The API is down'))->shouldHaveBeenCalledOnce();
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
        self::assertEquals([[[5 => new ProblemCount()], 'f1']], $this->stored);
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
     * It runs on an admin page, so it counts one batch of 100 a request, which the query claims and stores
     *
     * @test
     */
    public function it_counts_a_batch_of_the_entries_counted_with_other_code_or_never_again(): void
    {
        $first = $this->entry(3, [Severity::Warning]);
        $second = $this->entry(8, []);
        $unsaved = new Page();
        $counted = null;
        $this->query->countStale('f1', 100, Argument::type('callable'))->will(static function (array $arguments) use ($first, $second, $unsaved, &$counted): void {
            /** @var callable(list<EntryInterface>): array<int, ProblemCount> $count */
            $count = $arguments[2];
            $counted = $count([$first, $second, $unsaved]);
        })->shouldBeCalledOnce();

        $this->updater()->updateStale();

        self::assertEquals([3 => new ProblemCount(0, 1), 8 => new ProblemCount()], $counted);
    }

    /**
     * @param list<Severity> $severities the severities of the problems its working copy has, one kind each
     */
    private function entry(int $id, array $severities): EntryInterface
    {
        $entry = $this->prophesize(EntryInterface::class);
        $entry->getId()->willReturn($id);
        $entry->getUuid()->willReturn(sprintf('entry-%d', $id));
        $entry = $entry->reveal();

        $workingCopy = $this->prophesize(EntryInterface::class)->reveal();
        $this->workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);
        $this->workingCopyOf[spl_object_id($entry)] = $workingCopy;
        $this->problems[spl_object_id($workingCopy)] = array_map(
            static fn (Severity $severity): Problem => new Problem('some', $severity, $workingCopy, new AnyMessage()),
            $severities,
        );

        return $entry;
    }

    private function workingCopyOf(EntryInterface $entry): EntryInterface
    {
        return $this->workingCopyOf[spl_object_id($entry)];
    }

    private function unreadable(int $id, \Throwable $exception): EntryInterface
    {
        $entry = $this->prophesize(EntryInterface::class);
        $entry->getId()->willReturn($id);
        $entry = $entry->reveal();
        $this->workingCopies->getWorkingCopy($entry)->willThrow($exception);

        return $entry;
    }

    /**
     * @param list<ProblemKindInterface>|null $kinds
     */
    private function updater(?array $kinds = null, ?LoggerInterface $logger = null): ProblemCountUpdater
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

        return new ProblemCountUpdater($kinds ?? [$kind->reveal(), $otherKinds->reveal()], $this->workingCopies->reveal(), $this->query->reveal(), $this->fingerprint->reveal(), $logger ?? new NullLogger());
    }
}
