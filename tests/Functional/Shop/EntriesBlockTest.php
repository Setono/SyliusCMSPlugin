<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Entries block in the shop: a collection's entries the shopper may see, hand-picked or filled by an entry source,
 * each through its content type's card for the block's display. See "The Entries block", "Cards" and "Listings" in
 * docs/spec/collections.md and "Entry sources" in docs/spec/sources.md
 */
final class EntriesBlockTest extends WebTestCase
{
    use Blog;
    use Entries;
    use Faq;

    private const BLOCK = '0193a5be-0000-7000-8000-0000000000e1';

    /**
     * @test
     */
    public function it_shows_the_newest_visible_posts_of_the_blog_as_grid_cards(): void
    {
        $client = self::createClient();
        self::createBlogPost('Older post', 'older-post', published: true, publishedAt: new \DateTimeImmutable('2026-03-01 12:00', new \DateTimeZone('UTC')), excerpt: 'From the spring.');
        self::createBlogPost('Newer post', 'newer-post', published: true, publishedAt: new \DateTimeImmutable('2026-10-01 12:00', new \DateTimeZone('UTC')));
        self::createBlogPost('Draft post', 'draft-post');
        self::createPageWithBlocks('Blog', 'blog', [self::entries(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => 9])], published: true);

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        $block = $crawler->filter('.cms-entries');
        self::assertSame('cms-entries cms-entries--grid', $block->attr('class'));
        self::assertSame(['Newer post', 'Older post'], self::titles($crawler), 'The draft isn\'t published, so it\'s left out');

        $card = $crawler->filter('.cms-entries > .cms-entries__item > article.cms-blog-post-card.cms-blog-post-card--grid')->eq(1);
        self::assertSame('/en_US/blog/older-post', $card->filter('h3.cms-blog-post-card__title > a.cms-blog-post-card__link')->attr('href'));
        self::assertSame('2026-03-01T12:00:00+00:00', $card->filter('time.cms-blog-post-card__date')->attr('datetime'));
        self::assertSame('From the spring.', $card->filter('.cms-blog-post-card__excerpt')->text());
        self::assertCount(0, $crawler->filter('.cms-entries__item')->eq(0)->filter('.cms-blog-post-card__excerpt'), 'A post without an excerpt has none');
    }

    /**
     * @test
     */
    public function it_shows_the_posts_as_a_carousel_of_three_per_view(): void
    {
        $client = self::createClient();
        self::createBlogPost('A post', 'a-post', published: true);
        self::createPageWithBlocks('Blog', 'blog', [self::entries(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => 9], 'carousel')], published: true);

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        $carousel = $crawler->filter('.cms-entries.cms-entries--carousel > .cms-carousel');
        self::assertSame('3', $carousel->attr('data-cms-per-view'));
        self::assertCount(1, $carousel->filter('.cms-carousel__track > .cms-carousel__slide > .cms-entries__item > article.cms-blog-post-card--carousel'));
    }

    /**
     * Category filters join the reference table, which holds the published posts' categories
     *
     * @test
     */
    public function it_shows_only_the_posts_in_any_of_the_chosen_categories_each_once(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        $guides = self::createBlogCategory('Guides', 'guides', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);
        self::createBlogPost('In news', 'in-news', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-01-01'));
        self::createBlogPost('In both', 'in-both', [$guides, $news], published: true, publishedAt: new \DateTimeImmutable('2026-02-01'));
        self::createBlogPost('In recipes', 'in-recipes', [$recipes], published: true, publishedAt: new \DateTimeImmutable('2026-03-01'));
        self::createBlogPost('In none', 'in-none', published: true, publishedAt: new \DateTimeImmutable('2026-04-01'));
        self::createPageWithBlocks('News and guides', 'news-and-guides', [
            self::entries(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post', 'categories' => [$news->getUuid(), $guides->getUuid()]], '$limit' => 9]),
        ], published: true);

        self::assertSame(['In both', 'In news'], self::titles($client->request('GET', '/en_US/news-and-guides')));
    }

    /**
     * A category's URL is its archive: its tree holds an Entries block filled by In this category
     *
     * @test
     */
    public function it_shows_the_posts_in_the_category_on_the_category_s_page(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true, blocks: [
            self::entries(['$source' => 'entries_in_this_category', '$params' => new \stdClass(), '$limit' => 9]),
        ]);
        $guides = self::createBlogCategory('Guides', 'guides', published: true);
        self::createBlogPost('In news', 'in-news', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-01-01'));
        self::createBlogPost('Also in news', 'also-in-news', [$guides, $news], published: true, publishedAt: new \DateTimeImmutable('2026-02-01'));
        self::createBlogPost('In guides', 'in-guides', [$guides], published: true);
        self::createBlogPost('In news, unpublished', 'in-news-unpublished', [$news]);

        $crawler = $client->request('GET', '/en_US/blog/category/news');

        self::assertResponseIsSuccessful();
        self::assertSame(['Also in news', 'In news'], self::titles($crawler));
    }

    /**
     * @test
     */
    public function it_shows_the_other_posts_sharing_a_category_with_the_post_as_related_newest_first(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        $guides = self::createBlogCategory('Guides', 'guides', published: true);
        self::createBlogPost('Old in news', 'old-in-news', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-01-01'));
        self::createBlogPost('New in guides', 'new-in-guides', [$guides], published: true, publishedAt: new \DateTimeImmutable('2026-05-01'));
        self::createBlogPost('In neither', 'in-neither', published: true);
        self::createBlogPost('This post', 'this-post', [$news, $guides], published: true, publishedAt: new \DateTimeImmutable('2026-03-01'), blocks: [
            self::entries(['$source' => 'related_entries', '$params' => new \stdClass(), '$limit' => 3]),
        ]);

        $crawler = $client->request('GET', '/en_US/blog/this-post');

        self::assertResponseIsSuccessful();
        self::assertSame(['New in guides', 'Old in news'], self::titles($crawler), 'The post itself is left out');
    }

    /**
     * @test
     */
    public function it_shows_faq_items_as_an_accordion_of_details_with_the_accordion_item_s_classes(): void
    {
        $client = self::createClient();
        self::createFaqItem('How do I return an item?', 'Within 30 days.', published: true);
        self::createFaqItem('Do you ship abroad?', 'To the EU.', published: true);
        self::createFaqItem('Not yet answered?', 'Soon.');
        self::createPageWithBlocks('FAQ', 'faq', [self::entries(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'faq_item'], '$limit' => 9], 'accordion')], published: true);

        $crawler = $client->request('GET', '/en_US/faq');

        self::assertResponseIsSuccessful();
        $items = $crawler->filter('.cms-entries.cms-entries--accordion > .cms-entries__item > details.cms-accordion-item');
        self::assertSame(['How do I return an item?', 'Do you ship abroad?'], $items->filter('summary.cms-accordion-item__title')->each(static fn (Crawler $title): string => $title->text()), 'In the position editors dragged them into');
        self::assertSame('<p>Within 30 days.</p>', trim($items->eq(0)->filter('div.cms-accordion-item__content')->html()));
    }

    /**
     * @test
     */
    public function it_shows_hand_picked_entries_in_the_editor_s_order_leaving_out_those_the_shopper_may_not_see(): void
    {
        $client = self::createClient();
        $first = self::createBlogPost('First', 'first', published: true);
        $hidden = self::createBlogPost('Hidden', 'hidden');
        $second = self::createBlogPost('Second', 'second', published: true);
        self::createPageWithBlocks('Picks', 'picks', [self::entries([$second->getUuid(), $hidden->getUuid(), $first->getUuid()])], published: true);

        self::assertSame(['Second', 'First'], self::titles($client->request('GET', '/en_US/picks')));
    }

    /**
     * @test
     */
    public function it_shows_nothing_when_no_listed_entry_has_a_card_for_the_display(): void
    {
        $client = self::createClient();
        self::createFaqItem('How do I return an item?', 'Within 30 days.', published: true);
        self::createPageWithBlocks('FAQ', 'faq', [self::entries(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'faq_item'], '$limit' => 9])], published: true);

        $crawler = $client->request('GET', '/en_US/faq');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-entries'), 'FAQ items have no grid card');
    }

    /**
     * @param array<array-key, mixed>|\stdClass $entries the Entries field's stored value
     */
    private static function entries(array|\stdClass $entries, string $display = 'grid'): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'entries', 1, fields: ['entries' => $entries, 'display' => $display]);
    }

    /**
     * The titles of the blog post cards the Entries block shows, in order
     *
     * @return array<mixed>
     */
    private static function titles(Crawler $crawler): array
    {
        return $crawler->filter('.cms-entries .cms-blog-post-card__title')->each(static fn (Crawler $title): string => $title->text());
    }
}
