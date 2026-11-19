<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A listing paginates its entries with numbered ?page=N links that crawlers follow: the Entries block with paginate on,
 * $limit to a page. The pass before rendering counts its source, so a page that doesn't exist answers 404 before any
 * output. See "Pagination" in docs/spec/collections.md, "Rendering an entry at its own URL" in docs/spec/rendering.md
 * and "Head tags" in docs/spec/urls-and-seo.md
 */
final class PaginationTest extends WebTestCase
{
    use Blog;
    use Entries;

    private const BLOCK = '0193a5be-0000-7000-8000-0000000000e1';

    private const OTHER_BLOCK = '0193a5be-0000-7000-8000-0000000000e2';

    /**
     * @test
     */
    public function it_shows_the_second_page_of_the_blog_s_posts(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createBlog();

        $first = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        self::assertSame(self::postTitles(12, 4), self::titles($first), 'The newest 9 first');

        $second = $client->request('GET', '/en_US/blog?page=2');

        self::assertResponseIsSuccessful();
        self::assertSame(self::postTitles(3, 1), self::titles($second));
    }

    /**
     * @test
     */
    public function it_links_the_pages_after_the_entries_marking_the_current_one(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createBlog();

        $nav = $client->request('GET', '/en_US/blog')->filter('.cms-entries > nav.cms-pagination');

        self::assertCount(1, $nav);
        self::assertCount(0, $nav->nextAll(), 'The nav comes after the items');
        self::assertSame([['1', '/en_US/blog', 'page'], ['2', '/en_US/blog?page=2', null]], self::links($nav));
        self::assertSame('cms-pagination__link cms-pagination__link--current', $nav->filter('ol.cms-pagination__list > li.cms-pagination__item > a')->first()->attr('class'));
        self::assertCount(0, $nav->filter('.cms-pagination__previous'), 'The first page has no previous page');
        self::assertSame('/en_US/blog?page=2', $nav->filter('a.cms-pagination__next')->attr('href'));

        $nav = $client->request('GET', '/en_US/blog?page=2')->filter('.cms-entries > nav.cms-pagination');

        self::assertSame([['1', '/en_US/blog', null], ['2', '/en_US/blog?page=2', 'page']], self::links($nav));
        self::assertSame('/en_US/blog', $nav->filter('a.cms-pagination__previous')->attr('href'));
        self::assertCount(0, $nav->filter('.cms-pagination__next'), 'The last page has no next page');
    }

    /**
     * @test
     */
    public function it_puts_a_gap_in_the_page_links_for_the_pages_it_leaves_out(): void
    {
        $client = self::createClient();
        self::createPosts(10);
        self::createBlog(limit: 1);

        $nav = $client->request('GET', '/en_US/blog?page=5')->filter('nav.cms-pagination');

        self::assertSame(['1', '2', '3', '4', '5', '6', '7', '…', '10'], $nav->filter('.cms-pagination__list > li')->each(static fn (Crawler $item): string => trim($item->text())));
        self::assertCount(1, $nav->filter('li.cms-pagination__gap'));
        self::assertCount(0, $nav->filter('li.cms-pagination__gap a'));
    }

    /**
     * @test
     */
    public function it_shows_no_page_links_when_the_entries_fit_on_one_page(): void
    {
        $client = self::createClient();
        self::createPosts(9);
        self::createBlog();

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertCount(9, $crawler->filter('.cms-entries__item'));
        self::assertCount(0, $crawler->filter('.cms-pagination'));
    }

    /**
     * @test
     *
     * @dataProvider provideMissingPages
     */
    public function it_answers_404_for_a_page_that_does_not_exist(string $query): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createBlog();

        $client->request('GET', '/en_US/blog?' . $query);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function provideMissingPages(): iterable
    {
        yield 'past the last page' => ['page=99'];
        yield 'right after the last page' => ['page=3'];
        yield 'not a number' => ['page=abc'];
        yield 'zero' => ['page=0'];
        yield 'negative' => ['page=-1'];
        yield 'with a leading zero' => ['page=02'];
        yield 'a list' => ['page[]=2'];
    }

    /**
     * @test
     */
    public function it_redirects_page_1_to_the_url_without_it_keeping_the_rest_of_the_query_string(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createBlog();

        $client->request('GET', '/en_US/blog?page=1');

        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/en_US/blog');

        $client->request('GET', '/en_US/blog?utm_source=newsletter&page=1');

        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/en_US/blog?utm_source=newsletter');
    }

    /**
     * @test
     */
    public function it_shows_page_1_of_an_empty_list_and_no_other_page(): void
    {
        $client = self::createClient();
        self::createBlog();

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-entries'));

        $client->request('GET', '/en_US/blog?page=2');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_gives_an_entry_whose_paginated_block_is_hidden_only_page_1(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createPageWithBlocks('Blog', 'blog', [self::entries(conditions: [['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@no_group']]]])], published: true);

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-entries'), 'Guests don\'t see it');

        $client->request('GET', '/en_US/blog?page=2');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_gives_an_entry_without_a_paginated_block_only_page_1(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createPageWithBlocks('Blog', 'blog', [self::entries(paginate: false)], published: true);

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        self::assertCount(9, $crawler->filter('.cms-entries__item'));
        self::assertCount(0, $crawler->filter('.cms-pagination'));

        $client->request('GET', '/en_US/blog?page=2');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/blog?page=abc');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Publish refuses a second one, but older or imported data may hold it: the pass counts only the first in tree order,
     * and the second shows its first items on a page its list doesn't have
     *
     * @test
     */
    public function it_counts_only_the_first_paginated_block_of_the_tree(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createPageWithBlocks('Blog', 'blog', [self::entries(), self::entries(key: self::OTHER_BLOCK, limit: 20)], published: true);

        $crawler = $client->request('GET', '/en_US/blog?page=2');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.cms-pagination'));
        self::assertSame(self::postTitles(3, 1), self::titles($crawler->filter('.cms-entries')->eq(0)));
        self::assertSame(self::postTitles(12, 1), self::titles($crawler->filter('.cms-entries')->eq(1)));
    }

    /**
     * A snippet can't paginate, because a snippet in a slot on a taxon page would clash with Sylius's own ?page=N
     *
     * @test
     */
    public function it_does_not_page_a_paginated_block_of_a_snippet_the_entry_shows(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        $snippet = self::createSnippet('Latest posts', [self::entries()], published: true);
        self::createPageWithBlocks('Blog', 'blog', [new StoredBlock(self::OTHER_BLOCK, 'snippet', 1, fields: ['snippet' => $snippet->getUuid()])], published: true);

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        self::assertCount(9, $crawler->filter('.cms-entries__item'));
        self::assertCount(0, $crawler->filter('.cms-pagination'));

        $client->request('GET', '/en_US/blog?page=2');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * A category's URL is its archive: its tree holds an Entries block filled by In this category
     *
     * @test
     */
    public function it_paginates_a_category_s_archive(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true, blocks: [self::entries(['$source' => 'entries_in_this_category', '$params' => new \stdClass(), '$limit' => 2])]);
        self::createPosts(5, [$news]);
        self::createPosts(1, title: 'Elsewhere');

        $crawler = $client->request('GET', '/en_US/blog/category/news?page=3');

        self::assertResponseIsSuccessful();
        self::assertSame(['Post 1'], self::titles($crawler));
        self::assertSame('/en_US/blog/category/news?page=2', $crawler->filter('a.cms-pagination__previous')->attr('href'));

        $client->request('GET', '/en_US/blog/category/news?page=4');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_paginates_the_homepage_at_the_channel_s_root_url(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::setHomepageOf(self::createBlog(), 'WEB');

        $crawler = $client->request('GET', '/en_US/?page=2');

        self::assertResponseIsSuccessful();
        self::assertSame(self::postTitles(3, 1), self::titles($crawler));
        self::assertSame('/en_US/', $crawler->filter('a.cms-pagination__previous')->attr('href'));
        self::assertSame('http://localhost/en_US/?page=2', $crawler->filter('head link[rel="canonical"]')->attr('href'));

        $client->request('GET', '/en_US/?page=3');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/?page=1');
        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/en_US/');
    }

    /**
     * @test
     */
    public function it_gives_page_2_onwards_a_canonical_url_of_its_own_and_no_hreflang(): void
    {
        $client = self::createClient();
        self::createPosts(12);
        self::createBlog();

        $head = $client->request('GET', '/en_US/blog?utm_source=newsletter')->filter('head');

        self::assertSame('http://localhost/en_US/blog', $head->filter('link[rel="canonical"]')->attr('href'));
        self::assertCount(2, $head->filter('link[rel="alternate"][hreflang]'), 'en-US and x-default');

        $head = $client->request('GET', '/en_US/blog?page=2&utm_source=newsletter')->filter('head');

        self::assertSame('http://localhost/en_US/blog?page=2', $head->filter('link[rel="canonical"]')->attr('href'));
        self::assertCount(0, $head->filter('link[rel="alternate"][hreflang]'), 'Another locale\'s page 2 needn\'t hold the same entries');
    }

    /**
     * $count published posts, $title 1 the oldest
     *
     * @param list<BlogCategoryInterface> $categories
     */
    private static function createPosts(int $count, array $categories = [], string $title = 'Post'): void
    {
        for ($number = 1; $number <= $count; ++$number) {
            self::createBlogPost(sprintf('%s %d', $title, $number), sprintf('%s-%d', strtolower($title), $number), $categories, published: true, publishedAt: new \DateTimeImmutable(sprintf('2026-01-01 +%d days', $number)));
        }
    }

    /**
     * The blog: a page at blog with a paginated Entries block listing its posts, $limit to a page
     */
    private static function createBlog(int $limit = 9): PageInterface
    {
        return self::createPageWithBlocks('Blog', 'blog', [self::entries(limit: $limit)], published: true);
    }

    /**
     * An Entries block, paginated and listing the blog's posts, newest first, unless told otherwise
     *
     * @param array<array-key, mixed>|null $entries the Entries field's stored value
     * @param list<array<string, mixed>> $conditions
     */
    private static function entries(?array $entries = null, bool $paginate = true, int $limit = 9, string $key = self::BLOCK, array $conditions = []): StoredBlock
    {
        return new StoredBlock($key, 'entries', 1, fields: [
            'entries' => $entries ?? ['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => $limit],
            'display' => 'grid',
            'paginate' => $paginate,
        ], conditions: $conditions);
    }

    /**
     * Post $from down to Post $to, as the newest first lists them
     *
     * @return list<string>
     */
    private static function postTitles(int $from, int $to): array
    {
        return array_map(static fn (int $number): string => sprintf('Post %d', $number), range($from, $to));
    }

    /**
     * The titles of the blog post cards, in order
     *
     * @return array<mixed>
     */
    private static function titles(Crawler $crawler): array
    {
        return $crawler->filter('.cms-blog-post-card__title')->each(static fn (Crawler $title): string => $title->text());
    }

    /**
     * Each page link's text, URL and aria-current
     *
     * @return array<mixed>
     */
    private static function links(Crawler $nav): array
    {
        return $nav->filter('ol.cms-pagination__list > li.cms-pagination__item > a.cms-pagination__link')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href'), $link->attr('aria-current')]);
    }
}
