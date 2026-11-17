<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Admin\ProblemGroup;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\AnyMessage;

/**
 * The Overview's Needs attention groups the store's problems by kind. See "Overview" in docs/spec/admin.md
 */
final class ProblemGroupTest extends TestCase
{
    /**
     * @test
     */
    public function it_groups_problems_by_kind_in_the_order_they_were_found_with_the_groups_of_errors_first(): void
    {
        $unknownOnAbout = self::problem('unknown_block_type', Severity::Warning);
        $brokenOnAbout = self::problem('broken_reference', Severity::Error);
        $unknownOnContact = self::problem('unknown_block_type', Severity::Warning);
        $routeOnContact = self::problem('url_taken_by_route', Severity::Warning);
        $brokenOnContact = self::problem('broken_reference', Severity::Error);

        self::assertEquals([
            new ProblemGroup('broken_reference', Severity::Error, [$brokenOnAbout, $brokenOnContact]),
            new ProblemGroup('unknown_block_type', Severity::Warning, [$unknownOnAbout, $unknownOnContact]),
            new ProblemGroup('url_taken_by_route', Severity::Warning, [$routeOnContact]),
        ], ProblemGroup::byKind([$unknownOnAbout, $brokenOnAbout, $unknownOnContact, $routeOnContact, $brokenOnContact]));
    }

    /**
     * A project's kind may find both
     *
     * @test
     */
    public function it_counts_a_group_with_an_error_as_errors_even_when_its_kind_also_finds_warnings(): void
    {
        $warning = self::problem('project_kind', Severity::Warning);
        $error = self::problem('project_kind', Severity::Error);

        self::assertEquals(
            [new ProblemGroup('project_kind', Severity::Error, [$warning, $error])],
            ProblemGroup::byKind([$warning, $error]),
        );
    }

    /**
     * @test
     */
    public function it_has_no_groups_without_problems(): void
    {
        self::assertSame([], ProblemGroup::byKind([]));
    }

    private static function problem(string $kind, Severity $severity): Problem
    {
        return new Problem($kind, $severity, new Page(), new AnyMessage());
    }
}
