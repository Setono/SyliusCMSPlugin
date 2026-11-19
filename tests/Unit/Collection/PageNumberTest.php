<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\PageNumber;

/**
 * The page a ?page=N asks for: a whole number of 1 or more, and nothing else. See "Pagination" in
 * docs/spec/collections.md
 */
final class PageNumberTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider providePages
     */
    public function it_reads_a_whole_number_of_1_or_more_as_that_page(string $value, int $page): void
    {
        self::assertSame($page, PageNumber::parse($value));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public function providePages(): iterable
    {
        yield 'the first page' => ['1', 1];
        yield 'the second page' => ['2', 2];
        yield 'a page in the hundreds' => ['120', 120];
        yield 'the largest integer' => [(string) \PHP_INT_MAX, \PHP_INT_MAX];
    }

    /**
     * @test
     *
     * @dataProvider provideNonPages
     */
    public function it_reads_anything_else_as_no_page(mixed $value): void
    {
        self::assertNull(PageNumber::parse($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function provideNonPages(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-2'];
        yield 'with a plus sign' => ['+2'];
        yield 'with a leading zero' => ['02'];
        yield 'a fraction' => ['1.5'];
        yield 'an exponent' => ['1e3'];
        yield 'with spaces' => [' 2 '];
        yield 'letters' => ['abc'];
        yield 'a number with letters' => ['2abc'];
        yield 'past the largest integer' => ['9223372036854775808'];
        yield 'a list, as page[]=2 gives' => [['2']];
        yield 'nothing' => [null];
    }

    /**
     * @test
     */
    public function it_gives_page_1_the_entry_s_url_and_the_other_pages_the_url_with_the_page(): void
    {
        self::assertSame('/en_US/blog', PageNumber::url('/en_US/blog', 1));
        self::assertSame('/en_US/blog?page=2', PageNumber::url('/en_US/blog', 2));
        self::assertSame('https://shop.example/en_US/?page=12', PageNumber::url('https://shop.example/en_US/', 12));
        self::assertSame('/blog?channel=web&page=3', PageNumber::url('/blog?channel=web', 3), 'A URL a project gives entries keeps its query string');
    }
}
