<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Problem\PresetProblem;
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

    /**
     * The Content badge adds the presets' problems to the entries'
     *
     * @test
     */
    public function it_counts_the_problems_of_presets_as_it_counts_the_entries(): void
    {
        $preset = new Preset('Hero', [], code: 'hero');
        $problem = static fn (Severity $severity): PresetProblem => new PresetProblem('kind', $severity, $preset, new AnyMessage());

        self::assertEquals(new ProblemCount(1, 2), ProblemCount::of([$problem(Severity::Warning), $problem(Severity::Error), $problem(Severity::Warning)]));
    }
}
