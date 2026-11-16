<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;

final class ProblemFinderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_the_problems_of_every_kind_in_the_entrys_working_copy_errors_first(): void
    {
        $page = new Page();
        $workingCopy = clone $page;
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($page)->willReturn($workingCopy);

        $firstWarning = self::problem($workingCopy, 'removed', Severity::Warning);
        $firstError = self::problem($workingCopy, 'misfit', Severity::Error);
        $secondWarning = self::problem($workingCopy, 'removed', Severity::Warning);
        $secondError = self::problem($workingCopy, 'broken', Severity::Error);

        $finder = new ProblemFinder([
            self::kind($workingCopy, [$firstWarning, $firstError]),
            self::kind($workingCopy, []),
            self::kind($workingCopy, (static fn (): \Generator => yield from [$secondWarning, $secondError])()),
        ], $workingCopies->reveal());

        self::assertSame([$firstError, $secondError, $firstWarning, $secondWarning], $finder->findByEntry($page));
    }

    /**
     * @test
     */
    public function it_finds_nothing_without_kinds(): void
    {
        $page = new Page();
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($page)->willReturn($page);

        self::assertSame([], (new ProblemFinder([], $workingCopies->reveal()))->findByEntry($page));
    }

    /**
     * @param iterable<Problem> $problems
     */
    private static function kind(EntryInterface $expected, iterable $problems): ProblemKindInterface
    {
        return new class($expected, $problems) implements ProblemKindInterface {
            /**
             * @param iterable<Problem> $problems
             */
            public function __construct(private readonly EntryInterface $expected, private readonly iterable $problems)
            {
            }

            public function findIn(EntryInterface $entry): iterable
            {
                return $entry === $this->expected ? $this->problems : [];
            }
        };
    }

    private static function problem(EntryInterface $entry, string $kind, Severity $severity): Problem
    {
        return new Problem($kind, $severity, $entry, new AnyMessage());
    }
}
