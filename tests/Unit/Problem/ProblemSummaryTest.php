<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemSummary;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Symfony\Component\Translation\TranslatableMessage;

final class ProblemSummaryTest extends TestCase
{
    /**
     * @test
     */
    public function it_counts_problems_by_severity(): void
    {
        $page = new Page();
        $problem = static fn (Severity $severity): Problem => new Problem('kind', $severity, $page, new TranslatableMessage('Wrong'));

        $count = ProblemCount::of([$problem(Severity::Error), $problem(Severity::Warning), $problem(Severity::Error)]);

        self::assertEquals(new ProblemCount(2, 1), $count);
        self::assertSame(3, $count->total());
        self::assertEquals(new ProblemCount(), ProblemCount::of([]));
        self::assertSame(0, (new ProblemCount())->total());
    }

    /**
     * @test
     */
    public function it_gives_each_entrys_count_the_entries_that_need_attention_and_the_total(): void
    {
        $first = new Page();
        $second = new Page();
        $summary = new ProblemSummary([$first->getUuid() => new ProblemCount(1, 2), $second->getUuid() => new ProblemCount(0, 1)]);

        self::assertEquals(new ProblemCount(1, 2), $summary->of($first));
        self::assertEquals(new ProblemCount(), $summary->of(new Page()));
        self::assertSame([$first->getUuid(), $second->getUuid()], $summary->entryUuids());
        self::assertEquals(new ProblemCount(1, 3), $summary->total());
        self::assertEquals(new ProblemCount(), (new ProblemSummary())->total());
        self::assertSame([], (new ProblemSummary())->entryUuids());
    }
}
