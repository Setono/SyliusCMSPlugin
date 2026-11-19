<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * An editor creates the blog from the Blog page starting point at the slug blog, and a category from the starting
 * point its create dialog preselects, and a shopper browses from the blog to a post and on to the category's archive.
 * See "Shipped layouts, starting points and presets" and "Listings" in docs/spec/collections.md
 */
final class CollectionPresetsTest extends UITestCase
{
    use Blog;

    /**
     * @test
     */
    public function an_admin_user_creates_the_blog_and_a_category_from_their_starting_points_and_a_shopper_browses_from_the_blog_to_the_category_s_archive(): void
    {
        $this->signInAsAdmin();

        // The blog: a page from the Blog page starting point, at the slug blog
        $browser = $this->visit('/admin/cms/entries/page');
        $browser->getByRole('link', ['name' => 'Create'])->click();
        $create = $browser->getByRole('dialog', ['name' => 'New Page']);
        $startingPoints = $create->getByRole('group', ['name' => 'Starting point']);
        $thumbnail = $startingPoints->locator('img[src="/bundles/setonosyliuscmsplugin/thumbnail/preset/blog_page.svg"]');
        Expect::locator($thumbnail)->toHaveJSProperty('naturalWidth', 120);
        $startingPoints->getByText('Blog page', ['exact' => true])->click();
        Expect::locator($startingPoints->getByRole('radio', ['name' => 'Blog page']))->toBeChecked();
        $create->getByRole('textbox', ['name' => 'Title'])->fill('Blog');
        Expect::locator($create->getByRole('textbox', ['name' => 'Slug']))->toHaveValue('blog');
        $create->getByRole('button', ['name' => 'Create'])->click();

        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('Blog');
        Expect::locator($browser->frameLocator('iframe[title="Preview"]')->locator('.cms-tree > h2.cms-heading'))->toHaveText('Latest posts');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // A category, from the starting point its create dialog preselects
        $browser = $this->visit('/admin/cms/entries/blog_category');
        $browser->getByRole('link', ['name' => 'Create'])->click();
        $create = $browser->getByRole('dialog', ['name' => 'New Blog category']);
        $startingPoints = $create->getByRole('group', ['name' => 'Starting point']);
        Expect::locator($startingPoints->getByRole('radio', ['name' => 'Category archive']))->toBeChecked();
        $create->getByRole('textbox', ['name' => 'Title'])->fill('News');
        $create->getByRole('button', ['name' => 'Create'])->click();

        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('News');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // Some posts, two of them in the category
        $news = self::category('news');
        self::createBlogPost('Opening night', 'opening-night', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-03-01'));
        self::createBlogPost('Summer menu', 'summer-menu', published: true, publishedAt: new \DateTimeImmutable('2026-02-01'));
        self::createBlogPost('New chef', 'new-chef', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-01-01'));

        // The blog lists every post, newest first, each card linking to its post
        $shop = $this->visit('/en_US/blog');
        self::assertTexts(['Opening night', 'Summer menu', 'New chef'], $shop->locator('.cms-tree > .cms-entries.cms-entries--grid .cms-blog-post-card__title'));
        $shop->getByRole('link', ['name' => 'Opening night'])->click();

        // The post links to its category, whose page is its archive, listing only its posts
        Expect::locator($shop->locator('.cms-article__title'))->toHaveText('Opening night');
        $shop->locator('.cms-article__categories')->getByRole('link', ['name' => 'News'])->click();

        Expect::locator($shop->locator('.cms-category-header__title'))->toHaveText('News');
        self::assertStringEndsWith('/en_US/blog/category/news', $shop->url());
        self::assertTexts(['Opening night', 'New chef'], $shop->locator('.cms-tree > .cms-entries.cms-entries--grid .cms-blog-post-card__title'));
    }

    private static function category(string $slug): BlogCategoryInterface
    {
        /** @var RepositoryInterface<BlogCategoryInterface> $categories */
        $categories = self::getContainer()->get('setono_sylius_cms.repository.blog_category');
        foreach ($categories->findAll() as $category) {
            if ($category->getTranslation('en_US')->getSlug() === $slug) {
                return $category;
            }
        }

        self::fail(sprintf('There is no category with the slug "%s"', $slug));
    }
}
