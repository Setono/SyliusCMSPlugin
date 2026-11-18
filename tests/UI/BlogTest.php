<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * The blog, from the hub's Blog item: a category and a post in it, each created and published, and the post's page,
 * which links to its category. See "In the admin" and "Categories" in docs/spec/collections.md
 */
final class BlogTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_creates_a_category_and_a_post_in_it_from_the_blog_item_publishes_both_and_follows_the_posts_category_link(): void
    {
        $this->signInAsAdmin();

        // The Blog item lists the posts and the categories, switched by All, Posts and Categories
        $browser = $this->visit('/admin/cms');
        $browser->locator('[data-hub-menu]')->getByRole('link', ['name' => 'Blog'])->click();
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toContainText('Blog');
        $switch = $browser->getByRole('navigation', ['name' => 'Blog']);
        Expect::locator($switch->getByRole('link', ['name' => 'All']))->toHaveAttribute('aria-current', 'page');

        $switch->getByRole('link', ['name' => 'Categories'])->click();
        Expect::locator($browser->getByRole('navigation', ['name' => 'Blog'])->getByRole('link', ['name' => 'Categories']))->toHaveAttribute('aria-current', 'page');

        $browser->getByRole('link', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'New Blog category']);
        $dialog->getByLabel('Title')->fill('News');
        Expect::locator($dialog->getByLabel('Slug'))->toHaveValue('news');
        $dialog->getByRole('button', ['name' => 'Create'])->click();

        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('News');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // A post, in the category, picked from the blog's categories
        $browser = $this->visit('/admin/cms');
        $browser->locator('[data-hub-menu]')->getByRole('link', ['name' => 'Blog'])->click();
        $browser->getByRole('navigation', ['name' => 'Blog'])->getByRole('link', ['name' => 'Posts'])->click();
        $browser->getByRole('link', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'New Blog post']);
        $dialog->getByLabel('Title')->fill('Opening night');
        $dialog->getByRole('button', ['name' => 'Create'])->click();

        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('Opening night');
        $browser->getByRole('button', ['name' => 'Categories', 'exact' => true])->click();
        $browser->getByRole('option', ['name' => 'News'])->click();
        Expect::locator($browser->getByRole('list', ['name' => 'Categories'])->getByText('News', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // The post's page links to its category, whose page is its archive
        $shop = $this->visit('/en_US/blog/opening-night');
        Expect::locator($shop->locator('.cms-article__title'))->toHaveText('Opening night');
        Expect::locator($shop->locator('.cms-article__date'))->toBeVisible();

        $shop->locator('.cms-article__categories')->getByRole('link', ['name' => 'News'])->click();

        Expect::locator($shop->locator('.cms-category-header__title'))->toHaveText('News');
        self::assertStringEndsWith('/en_US/blog/category/news', $shop->url());
    }
}
