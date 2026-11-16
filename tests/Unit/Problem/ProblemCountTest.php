<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;

final class ProblemCountTest extends TestCase
{
    /**
     * @test
     */
    public function it_counts_problems_by_severity(): void
    {
        $page = new Page();
        $problem = static fn (Severity $severity): Problem => new Problem('kind', $severity, $page, new AnyMessage());

        $count = ProblemCount::of([$problem(Severity::Error), $problem(Severity::Warning), $problem(Severity::Error)]);

        self::assertEquals(new ProblemCount(2, 1), $count);
        self::assertSame(3, $count->total());
        self::assertEquals(new ProblemCount(), ProblemCount::of([]));
        self::assertSame(0, (new ProblemCount())->total());
    }
}
