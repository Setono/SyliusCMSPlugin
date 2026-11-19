<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Entries;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Snippet;
use Setono\SyliusCMSPlugin\Collection\PageNotFoundException;
use Setono\SyliusCMSPlugin\Collection\PaginatedBlock;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * The paginated Entries block of an entry shown at its own URL: an Entries block that paginates, in the entry's own
 * tree, that shows. See "Pagination" in docs/spec/collections.md and "Rendering an entry at its own URL" in
 * docs/spec/rendering.md
 */
final class PaginatedBlockTest extends TestCase
{
    private const SNIPPET = '0193a5be-0000-7000-8000-00000000005e';

    private Page $page;

    protected function setUp(): void
    {
        $this->page = new Page();
    }

    /**
     * @test
     */
    public function it_finds_the_paginated_entries_block_of_the_entry_s_own_tree_among_the_blocks_that_show(): void
    {
        $paginated = $this->entries('e2', paginate: true);
        $views = [$this->heading('h1'), $this->entries('e1'), $this->group('g1', $this->heading('h2'), $paginated)];

        self::assertSame($paginated, PaginatedBlock::in($views, $this->context()));
        self::assertSame($paginated->block->entries ?? null, PaginatedBlock::listOf($paginated, $this->context()));
    }

    /**
     * Publish refuses a second one, but older or imported data may still hold it
     *
     * @test
     */
    public function it_finds_the_first_one_in_tree_order(): void
    {
        $first = $this->entries('e1', paginate: true);

        self::assertSame($first, PaginatedBlock::in([$this->group('g1', $first), $this->entries('e2', paginate: true)], $this->context()));
    }

    /**
     * @test
     */
    public function it_finds_none_when_the_entries_blocks_do_not_paginate(): void
    {
        $views = [$this->entries('e1'), $this->heading('h1')];

        self::assertNull(PaginatedBlock::in($views, $this->context()));
        self::assertNull(PaginatedBlock::listOf($views[0], $this->context()));
        self::assertNull(PaginatedBlock::listOf($views[1], $this->context()));
    }

    /**
     * A snippet can't paginate, because a snippet in a slot on a taxon page would clash with Sylius's own ?page=N
     *
     * @test
     */
    public function it_leaves_out_the_blocks_of_a_snippet_the_tree_shows(): void
    {
        $inSnippet = $this->entries('e1', paginate: true, entry: self::SNIPPET);
        $snippet = new BlockView('s1', 'snippet', new Snippet(), null, [$inSnippet], entry: $this->page->getUuid());

        self::assertNull(PaginatedBlock::in([$snippet], $this->context()));
        self::assertNull(PaginatedBlock::listOf($inSnippet, $this->context()));
    }

    /**
     * Such as a snippet's blocks in a slot, on a page that isn't an entry's
     *
     * @test
     */
    public function it_finds_none_without_an_entry_in_the_host_page_context(): void
    {
        $paginated = $this->entries('e1', paginate: true);

        self::assertNull(PaginatedBlock::in([$paginated], Rendering::context()));
        self::assertNull(PaginatedBlock::listOf($paginated, Rendering::context()));
    }

    /**
     * @test
     */
    public function it_lets_every_page_of_the_paginated_block_s_list_show(): void
    {
        $views = [$this->entries('e1', paginate: true, list: self::posts(12, 9))];

        PaginatedBlock::assertPageExists($views, $this->context(1));
        PaginatedBlock::assertPageExists($views, $this->context(2));

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('Page 3 doesn\'t exist: the list has 2 pages');

        PaginatedBlock::assertPageExists($views, $this->context(3));
    }

    /**
     * Page 1 always exists, so it's never counted before rendering
     *
     * @test
     */
    public function it_lets_page_1_show_without_counting_even_when_the_list_is_empty(): void
    {
        $uncountable = ItemList::lazy(static fn (): AdapterInterface => new /** @implements AdapterInterface<EntryInterface> */ class() implements AdapterInterface {
            public function getNbResults(): int
            {
                throw new \LogicException('Page 1 is never counted before rendering');
            }

            public function getSlice(int $offset, int $length): iterable
            {
                return [];
            }
        }, 9);

        PaginatedBlock::assertPageExists([$this->entries('e1', paginate: true, list: $uncountable)], $this->context(1));
        PaginatedBlock::assertPageExists([], $this->context(1));

        $this->addToAssertionCount(2);
    }

    /**
     * @test
     */
    public function it_lets_an_empty_list_have_only_page_1(): void
    {
        $this->expectException(PageNotFoundException::class);

        PaginatedBlock::assertPageExists([$this->entries('e1', paginate: true)], $this->context(2));
    }

    /**
     * Its conditions hide it, or there's none
     *
     * @test
     */
    public function it_lets_an_entry_without_a_paginated_block_that_shows_have_only_page_1(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('Page 2 doesn\'t exist: the entry has no paginated Entries block that shows');

        PaginatedBlock::assertPageExists([$this->entries('e1', list: self::posts(12, 9))], $this->context(2));
    }

    /**
     * A source's list of $count posts, $limit to a page
     *
     * @param int<1, max> $limit
     *
     * @return ItemList<EntryInterface>
     */
    private static function posts(int $count, int $limit): ItemList
    {
        return ItemList::lazy(static fn (): AdapterInterface => new ArrayAdapter(self::pages($count)), $limit);
    }

    /**
     * @return list<EntryInterface>
     */
    private static function pages(int $count): array
    {
        $pages = [];
        for ($number = 1; $number <= $count; ++$number) {
            $pages[] = new Page();
        }

        return $pages;
    }

    /**
     * @param ItemList<EntryInterface>|null $list
     */
    private function entries(string $key, bool $paginate = false, ?string $entry = null, ?ItemList $list = null): BlockView
    {
        $block = new Entries();
        $block->entries = $list ?? ItemList::of(self::pages(0));
        $block->paginate = $paginate;

        return new BlockView($key, 'entries', $block, null, [], entry: $entry ?? $this->page->getUuid());
    }

    private function heading(string $key): BlockView
    {
        return new BlockView($key, 'heading', new Heading(), null, [], entry: $this->page->getUuid());
    }

    private function group(string $key, BlockView ...$children): BlockView
    {
        return new BlockView($key, 'group', new Group(), null, array_values($children), entry: $this->page->getUuid());
    }

    /**
     * @param int<1, max> $page
     */
    private function context(int $page = 1): RenderContext
    {
        return Rendering::context(hostPageContext: ['entry' => $this->page])->withPage($page);
    }
}
