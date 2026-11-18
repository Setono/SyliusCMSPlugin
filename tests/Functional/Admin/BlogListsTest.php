<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The hub's Blog item lists the blog's posts and its categories, switched by All, Posts and Categories. See "In the
 * admin" in docs/spec/collections.md and "Navigation" in docs/spec/admin.md
 */
final class BlogListsTest extends WebTestCase
{
    use Blog;
    use Entries;

    /**
     * @test
     */
    public function it_lists_the_blogs_posts_and_categories_together_from_the_blog_item(): void
    {
        $client = self::signedIn();
        $news = self::createBlogCategory('News', 'news', published: true);
        $post = self::createBlogPost('Opening night', 'opening-night', [$news]);
        self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', '/admin/cms');
        $blog = $crawler->filter('[data-hub-menu] a.item')->reduce(static fn (Crawler $item): bool => '/admin/cms/collections/blog_post' === $item->attr('href'));
        self::assertCount(1, $blog);
        self::assertSame('2', $blog->filter('.label')->text());

        $crawler = $client->click($blog->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Blog');
        self::assertEqualsCanonicalizing(
            [[$post->getUuid(), 'Blog post'], [$news->getUuid(), 'Blog category']],
            $crawler->filter('table tbody tr')->each(static fn (Crawler $row): array => [(string) $row->filter('[data-entry]')->attr('data-entry'), trim($row->filter('[data-column="type"]')->text())]),
        );
        self::assertSame(
            [['All', '/admin/cms/collections/blog_post', true], ['Posts', '/admin/cms/entries/blog_post', false], ['Categories', '/admin/cms/entries/blog_category', false]],
            self::switches($crawler),
        );
        self::assertSame('Blog', self::current($crawler));
        // Creating from All asks whether to create a post or a category
        self::assertSame(
            [['Blog post', '/admin/cms/entries/blog_post/new'], ['Blog category', '/admin/cms/entries/blog_category/new']],
            $crawler->filter('dialog#cms-create-entry [data-cms-create-entry-type]')->each(static fn (Crawler $type): array => [trim($type->text()), (string) $type->attr('href')]),
        );
    }

    /**
     * @test
     */
    public function it_switches_to_the_posts_and_to_the_categories(): void
    {
        $client = self::signedIn();
        $news = self::createBlogCategory('News', 'news', published: true);
        $post = self::createBlogPost('Opening night', 'opening-night', [$news]);

        $crawler = $client->request('GET', '/admin/cms/collections/blog_post');
        $crawler = $client->click($crawler->filter('[data-cms-collection-switch] a')->selectLink('Posts')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Blog');
        self::assertSame([$post->getUuid()], self::listedUuids($crawler));
        self::assertSame([['All', '/admin/cms/collections/blog_post', false], ['Posts', '/admin/cms/entries/blog_post', true], ['Categories', '/admin/cms/entries/blog_category', false]], self::switches($crawler));
        self::assertSame('Blog', self::current($crawler), 'The Blog item stays current');
        self::assertSame('/admin/cms/entries/blog_post/new', $crawler->filter('[data-cms-create-entry]')->attr('href'));

        $crawler = $client->click($crawler->filter('[data-cms-collection-switch] a')->selectLink('Categories')->link());

        self::assertResponseIsSuccessful();
        self::assertSame([$news->getUuid()], self::listedUuids($crawler));
        self::assertSame([['All', '/admin/cms/collections/blog_post', false], ['Posts', '/admin/cms/entries/blog_post', false], ['Categories', '/admin/cms/entries/blog_category', true]], self::switches($crawler));
        self::assertSame('Blog', self::current($crawler));
    }

    /**
     * Only a collection with categories has a list of its own beside its content type's
     *
     * @test
     */
    public function it_has_no_switch_for_a_collection_without_categories(): void
    {
        $client = self::signedIn();

        $crawler = $client->request('GET', '/admin/cms/entries/store_location');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Store locations');
        self::assertCount(0, $crawler->filter('[data-cms-collection-switch]'));

        $client->request('GET', '/admin/cms/collections/store_location');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/cms/collections/page');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/cms/collections/blog_category');
        self::assertResponseStatusCodeSame(404, 'A collection is named by its entries\' content type');
    }

    private static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * @return list<array{string, string, bool}> each switch's name, link and whether it's the list shown
     */
    private static function switches(Crawler $crawler): array
    {
        return array_values($crawler->filter('[data-cms-collection-switch] a')->each(static fn (Crawler $link): array => [
            trim($link->text()),
            (string) $link->attr('href'),
            'page' === $link->attr('aria-current'),
        ]));
    }

    /**
     * The name of the hub menu's current item
     */
    private static function current(Crawler $crawler): string
    {
        $item = $crawler->filter('[data-hub-menu] a.item.active');
        self::assertCount(1, $item);

        return trim(substr($item->text(), 0, -strlen($item->filter('.label')->text())));
    }

    /**
     * @return list<string>
     */
    private static function listedUuids(Crawler $crawler): array
    {
        return array_values($crawler->filter('table tbody [data-entry]')->each(static fn (Crawler $title): string => (string) $title->attr('data-entry')));
    }
}
