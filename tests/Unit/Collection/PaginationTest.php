<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\PageLink;
use Setono\SyliusCMSPlugin\Collection\Pagination;

/**
 * The page links of a paginated Entries block: the first and the last page, the pages around the current one, a gap
 * for the pages left out between them, and the links to the previous and the next page. See "The Entries block" in
 * docs/spec/collections.md
 */
final class PaginationTest extends TestCase
{
    /**
     * @test
     */
    public function it_links_every_page_when_there_are_few(): void
    {
        $pagination = self::pagination(page: 3, pages: 5);

        self::assertSame(['1', '2', '[3]', '4', '5'], self::links($pagination));
        self::assertSame('/blog?page=2', $pagination->previous?->url);
        self::assertSame('/blog?page=4', $pagination->next?->url);
    }

    /**
     * @test
     */
    public function it_links_page_1_without_a_query_string(): void
    {
        $pagination = self::pagination(page: 2, pages: 3);

        self::assertSame('/blog', $pagination->links[0]->url);
        self::assertSame('/blog', $pagination->previous?->url);
        self::assertSame('/blog?page=3', $pagination->links[2]->url);
    }

    /**
     * @test
     */
    public function it_leaves_out_the_previous_link_on_the_first_page(): void
    {
        $pagination = self::pagination(page: 1, pages: 10);

        self::assertSame(['[1]', '2', '3', '…', '10'], self::links($pagination));
        self::assertNull($pagination->previous);
        self::assertSame(2, $pagination->next?->page);
    }

    /**
     * @test
     */
    public function it_leaves_out_the_next_link_on_the_last_page(): void
    {
        $pagination = self::pagination(page: 10, pages: 10);

        self::assertSame(['1', '…', '8', '9', '[10]'], self::links($pagination));
        self::assertSame(9, $pagination->previous?->page);
        self::assertNull($pagination->next);
    }

    /**
     * @test
     */
    public function it_puts_a_gap_on_each_side_of_the_pages_around_the_current_one(): void
    {
        self::assertSame(['1', '…', '8', '9', '[10]', '11', '12', '…', '20'], self::links(self::pagination(page: 10, pages: 20)));
    }

    /**
     * A gap would hide only one page, which takes no more room than the gap
     *
     * @test
     */
    public function it_links_the_one_page_a_gap_would_hide(): void
    {
        self::assertSame(['1', '2', '3', '4', '[5]', '6', '7', '…', '10'], self::links(self::pagination(page: 5, pages: 10)));
        self::assertSame(['1', '…', '4', '5', '[6]', '7', '8', '9', '10'], self::links(self::pagination(page: 6, pages: 10)));
    }

    /**
     * @test
     */
    public function it_links_only_page_1_of_a_list_with_one_page(): void
    {
        $pagination = self::pagination(page: 1, pages: 1);

        self::assertSame(['[1]'], self::links($pagination));
        self::assertNull($pagination->previous);
        self::assertNull($pagination->next);
    }

    /**
     * @test
     */
    public function it_gives_the_entries_of_the_current_page(): void
    {
        self::assertSame([7, 8, 9], [...self::pagination(page: 3, pages: 4)->entries()]);
    }

    /**
     * Each link as its page, the current one in brackets, and a gap as …
     *
     * @return list<string>
     */
    private static function links(Pagination $pagination): array
    {
        return array_map(static fn (PageLink $link): string => match (true) {
            null === $link->page => '…',
            $link->current => sprintf('[%d]', $link->page),
            default => (string) $link->page,
        }, $pagination->links);
    }

    /**
     * Page $page of a list of 3 to a page with $pages pages
     *
     * @param int<1, max> $page
     */
    private static function pagination(int $page, int $pages): Pagination
    {
        /** @var Pagerfanta<int> $pager */
        $pager = new Pagerfanta(new ArrayAdapter(range(1, $pages * 3)));
        $pager->setMaxPerPage(3);
        $pager->setCurrentPage($page);

        return Pagination::of($pager, static fn (int $page): string => 1 === $page ? '/blog' : '/blog?page=' . $page);
    }
}
