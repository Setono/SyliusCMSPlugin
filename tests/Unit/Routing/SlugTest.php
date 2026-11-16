<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Routing\Slug;

final class SlugTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideValidSlugs
     */
    public function it_accepts_a_slug_with_or_without_slashes_inside(string $slug): void
    {
        self::assertTrue(Slug::isValid($slug));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidSlugs(): iterable
    {
        yield 'one segment' => ['about-us'];
        yield 'one character' => ['a'];
        yield 'a slash inside' => ['about/team'];
        yield 'several slashes inside' => ['about/team/europe'];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidSlugs
     */
    public function it_refuses_a_slug_that_is_empty_starts_or_ends_with_a_slash_or_has_two_slashes_in_a_row(string $slug): void
    {
        self::assertFalse(Slug::isValid($slug));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'a slash alone' => ['/'];
        yield 'a slash first' => ['/about'];
        yield 'a slash last' => ['about/'];
        yield 'two slashes in a row' => ['about//team'];
        yield 'a line break' => ["about\nus"];
    }

    /**
     * @test
     */
    public function it_gives_its_pattern_without_the_anchors_as_its_html_pattern(): void
    {
        self::assertSame(1, preg_match('#^' . Slug::HTML_PATTERN . '$#', 'about/team'));
        self::assertSame(0, preg_match('#^' . Slug::HTML_PATTERN . '$#', 'about//team'));
    }
}
