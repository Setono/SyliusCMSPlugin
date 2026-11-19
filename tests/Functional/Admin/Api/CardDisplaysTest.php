<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;

/**
 * card_displays: the displays the Entries block offers for what its entries field holds right now, which the builder
 * offers in its display select: those of the content type a source lists, those every picked entry's content type has,
 * or every display when it can't tell. See "The Entries block" in docs/spec/collections.md and "JSON endpoints" in
 * docs/spec/builder.md
 */
final class CardDisplaysTest extends BuilderApiTestCase
{
    use Blog;
    use Faq;

    /**
     * @test
     */
    public function it_offers_the_displays_of_the_content_type_a_source_lists(): void
    {
        $client = self::createClient();
        $page = self::createPage('Blog', 'blog');
        self::openBuilder($client, $page);

        self::assertSame(['displays' => ['grid', 'carousel']], self::json($client, 'POST', self::uri($page), ['entries' => ['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => 9]]));
        self::assertResponseIsSuccessful();
        self::assertSame(['displays' => ['accordion']], self::json($client, 'POST', self::uri($page), ['entries' => ['$source' => 'entries_from_collection', '$params' => ['contentType' => 'faq_item'], '$limit' => 9]]));
    }

    /**
     * In this category lists the posts of the blog category it's in
     *
     * @test
     */
    public function it_offers_the_displays_of_the_content_type_a_source_lists_on_the_entry_s_page(): void
    {
        $client = self::createClient();
        $category = self::createBlogCategory('News', 'news');
        self::openBuilder($client, $category);

        self::assertSame(['displays' => ['grid', 'carousel']], self::json($client, 'POST', self::uri($category), ['entries' => ['$source' => 'entries_in_this_category', '$params' => new \stdClass(), '$limit' => 9]]));
    }

    /**
     * @test
     */
    public function it_offers_the_displays_every_picked_entry_s_content_type_has(): void
    {
        $client = self::createClient();
        $post = self::createBlogPost('A post', 'a-post', published: true);
        $item = self::createFaqItem('A question', 'An answer.', published: true);
        $page = self::createPage('Picks', 'picks');
        self::openBuilder($client, $page);

        self::assertSame(['displays' => ['grid', 'carousel']], self::json($client, 'POST', self::uri($page), ['entries' => [$post->getUuid()]]));
        self::assertSame(['displays' => []], self::json($client, 'POST', self::uri($page), ['entries' => [$post->getUuid(), $item->getUuid()]]), 'Blog posts and FAQ items share no display');
    }

    /**
     * @test
     */
    public function it_offers_every_display_when_it_cannot_tell(): void
    {
        $client = self::createClient();
        $page = self::createPage('Picks', 'picks');
        self::openBuilder($client, $page);

        self::assertSame(['displays' => ['grid', 'carousel', 'accordion']], self::json($client, 'POST', self::uri($page), ['entries' => []]));
        self::assertSame(['displays' => ['grid', 'carousel', 'accordion']], self::json($client, 'POST', self::uri($page), ['entries' => null]));
        self::assertSame(['displays' => ['grid', 'carousel', 'accordion']], self::json($client, 'POST', self::uri($page), ['entries' => ['$source' => 'entries_from_collection', '$params' => new \stdClass(), '$limit' => 9]]));
    }

    /**
     * @test
     */
    public function it_needs_a_valid_preview_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('Blog', 'blog');
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/cards/displays', ['entries' => []]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'The request has no valid preview token'], $response);
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('Blog', 'blog');

        self::json($client, 'POST', self::uri($page), ['entries' => []]);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_refuses_a_body_that_is_not_json(): void
    {
        $client = self::createClient();
        $page = self::createPage('Blog', 'blog');
        self::openBuilder($client, $page);

        $client->request('POST', self::uri($page), [], [], ['CONTENT_TYPE' => 'application/json'], '{');

        self::assertResponseStatusCodeSame(400);
    }

    private static function uri(EntryInterface $entry): string
    {
        return '/admin/cms/api/cards/displays?_cms_preview=' . self::previewToken($entry->getUuid());
    }
}
