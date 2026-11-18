<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The blog in the shop: a post at its URL under "blog" in the Article layout, and a category at its URL under
 * "blog/category" in its Standard layout. See "Shipped layouts" in docs/spec/layouts-and-presets.md
 */
final class BlogTest extends WebTestCase
{
    use Blog;
    use Entries;
    use MediaItems;

    /**
     * @test
     */
    public function it_renders_a_post_in_the_article_layout_with_its_date_category_links_and_featured_image(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);
        $scarf = $this->uploadMediaItem('scarf.png', self::png());
        self::createBlogPost(
            'Ten ways to style a scarf',
            'ten-ways-to-style-a-scarf',
            [$recipes, $news],
            published: true,
            publishedAt: new \DateTimeImmutable('2026-11-13 09:00:00', new \DateTimeZone('UTC')),
            featuredImage: $scarf->getUuid(),
        );

        $crawler = $client->request('GET', '/en_US/blog/ten-ways-to-style-a-scarf');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-article .cms-article__title', 'Ten ways to style a scarf');
        self::assertSelectorTextSame('.cms-article__date', 'November 13, 2026');
        self::assertStringStartsWith('2026-11-13T', (string) $crawler->filter('.cms-article__date')->attr('datetime'));
        self::assertSame(
            [['Recipes', '/en_US/blog/category/recipes'], ['News', '/en_US/blog/category/news']],
            $crawler->filter('.cms-article__categories a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]),
        );
        self::assertCount(1, $crawler->filter('.cms-article__header picture.cms-article__image img'));
        self::assertCount(1, $crawler->filter('.cms-article .cms-tree'));
    }

    /**
     * Rendering skips a category the shopper may not see, and one that was deleted, like any broken reference
     *
     * @test
     */
    public function it_leaves_out_the_categories_that_are_not_published_or_were_deleted(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        $drafts = self::createBlogCategory('Drafts', 'drafts');
        $gone = self::createBlogCategory('Gone', 'gone', published: true);
        self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', [$drafts, $gone, $news], published: true);

        /** @var EntryDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EntryDeleterInterface::class);
        $deleter->delete($gone);

        $crawler = $client->request('GET', '/en_US/blog/ten-ways-to-style-a-scarf');

        self::assertResponseIsSuccessful();
        self::assertSame(['News'], $crawler->filter('.cms-article__categories li')->each(static fn (Crawler $item): string => $item->text()));
    }

    /**
     * @test
     */
    public function it_renders_a_post_without_a_date_categories_or_image_with_its_title_only(): void
    {
        $client = self::createClient();
        $post = self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', published: true);
        $post->setPublishedAt(null);
        self::entityManager()->flush();

        $client->request('GET', '/en_US/blog/ten-ways-to-style-a-scarf');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-article__title', 'Ten ways to style a scarf');
        self::assertSelectorNotExists('.cms-article__date');
        self::assertSelectorNotExists('.cms-article__categories');
        self::assertSelectorNotExists('.cms-article__image');
    }

    /**
     * @test
     */
    public function it_renders_a_category_in_its_standard_layout_with_its_title_and_description(): void
    {
        $client = self::createClient();
        self::createBlogCategory('Recipes', 'recipes', 'What we cook in the workshop\'s kitchen.', published: true);

        $client->request('GET', '/en_US/blog/category/recipes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-category-header__title', 'Recipes');
        self::assertSelectorTextSame('.cms-category-header__description', 'What we cook in the workshop\'s kitchen.');
        self::assertSelectorExists('.cms-tree');
    }

    /**
     * The meta description falls back to the post's excerpt, its descriptionField
     *
     * @test
     */
    public function it_describes_a_post_by_its_excerpt(): void
    {
        $client = self::createClient();
        self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', published: true, excerpt: 'From office to weekend.');

        $crawler = $client->request('GET', '/en_US/blog/ten-ways-to-style-a-scarf');

        self::assertResponseIsSuccessful();
        self::assertSame('From office to weekend.', $crawler->filter('meta[name="description"]')->attr('content'));
    }
}
