<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Problem\PresetProblem;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;

/**
 * How bad a problem is, the errors listed first. See "Problems" in docs/spec/admin.md
 */
final class SeverityTest extends TestCase
{
    /**
     * @test
     */
    public function it_puts_the_errors_first_each_in_the_order_given(): void
    {
        $page = new Page();
        $warning = new Problem('unknown_block_type', Severity::Warning, $page, new AnyMessage());
        $error = new Problem('broken_reference', Severity::Error, $page, new AnyMessage());
        $otherWarning = new Problem('url_taken_by_route', Severity::Warning, $page, new AnyMessage());
        $otherError = new Problem('misfit_value', Severity::Error, $page, new AnyMessage());

        self::assertSame([$error, $otherError, $warning, $otherWarning], Severity::errorsFirst([$warning, $error, $otherWarning, $otherError]));
        self::assertSame([], Severity::errorsFirst([]));
    }

    /**
     * @test
     */
    public function it_puts_the_errors_of_presets_first_as_it_does_the_entries(): void
    {
        $preset = new Preset('Hero', [], code: 'hero');
        $warning = new PresetProblem('preset_removed_block_type', Severity::Warning, $preset, new AnyMessage());
        $error = new PresetProblem('preset_invalid', Severity::Error, $preset, new AnyMessage());

        self::assertSame([$error, $warning], Severity::errorsFirst((static function () use ($warning, $error): \Generator {
            yield $warning;
            yield $error;
        })()));
    }
}
