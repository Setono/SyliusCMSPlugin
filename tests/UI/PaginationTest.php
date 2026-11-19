<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A listing paginates its entries with numbered ?page=N links: an editor splits the blog's Entries block into pages of
 * the number it shows, and shoppers page through the posts. See "Pagination" and "The Entries block" in
 * docs/spec/collections.md
 */
final class PaginationTest extends UITestCase
{
    use Blog;

    /**
     * @test
     */
    public function it_lets_an_admin_user_split_the_blog_into_pages_that_shoppers_page_through(): void
    {
        for ($number = 1; $number <= 12; ++$number) {
            self::createBlogPost(sprintf('Post %d', $number), sprintf('post-%d', $number), published: true, publishedAt: new \DateTimeImmutable(sprintf('2026-01-01 +%d days', $number)));
        }
        $this->signInAsAdmin();
        $entry = self::createPageWithBlocks('Blog', 'blog', [new StoredBlock('0193a5be-0000-7000-8000-0000000000e1', 'entries', 1, fields: [
            'entries' => ['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => 9],
            'display' => 'grid',
        ])]);

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('navigation')->getByRole('button', ['name' => 'Entries', 'exact' => true])->click();
        $fields = $page->getByRole('complementary');
        Expect::locator($fields->getByRole('spinbutton', ['name' => 'Items to show']))->toHaveValue('9');
        $fields->getByRole('checkbox', ['name' => 'Split into pages'])->check();
        Expect::locator($page->frameLocator('iframe[title="Preview"]')->locator('nav.cms-pagination'))->toBeVisible();

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        // Page 1: the newest 9 posts, and the links to both pages
        $shop = $this->visit('/en_US/blog');
        $cards = $shop->locator('.cms-tree > .cms-entries.cms-entries--grid > .cms-entries__item .cms-blog-post-card__title');
        Expect::locator($cards)->toHaveCount(9);
        $pages = $shop->getByRole('navigation', ['name' => 'Pages']);
        self::assertTexts(['1', '2'], $pages->locator('.cms-pagination__list .cms-pagination__link'));
        Expect::locator($pages->getByRole('link', ['name' => '1', 'exact' => true]))->toHaveAttribute('aria-current', 'page');
        Expect::locator($pages->getByRole('link', ['name' => 'Previous']))->toHaveCount(0);

        // Page 2: the other 3, with page 2 as the current one
        $pages->getByRole('link', ['name' => '2', 'exact' => true])->click();
        Expect::locator($pages->getByRole('link', ['name' => '2', 'exact' => true]))->toHaveAttribute('aria-current', 'page');
        self::assertStringEndsWith('/en_US/blog?page=2', $shop->url());
        self::assertTexts(['Post 3', 'Post 2', 'Post 1'], $cards);
        Expect::locator($pages->getByRole('link', ['name' => '1', 'exact' => true]))->not()->toHaveAttribute('aria-current', 'page');
        Expect::locator($pages->getByRole('link', ['name' => 'Next']))->toHaveCount(0);

        // And back
        $pages->getByRole('link', ['name' => 'Previous'])->click();
        Expect::locator($cards)->toHaveCount(9);
    }
}
