<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;

/**
 * The builder's category picker: a blog post's categories field names the blog category content type, so its picker
 * searches only the blog's categories, and names those a post holds, flagging a deleted one, which the search doesn't
 * find. See "Right pane: the selection" in docs/spec/builder.md
 */
final class CategoryPickerTest extends BuilderApiTestCase
{
    use Blog;
    use Faq;
    use StoreLocations;

    /**
     * @test
     */
    public function it_tells_the_picker_of_a_posts_categories_to_search_the_blog_categories(): void
    {
        $client = self::signedIn();

        $registry = (array) self::json($client, 'GET', '/admin/cms/api/registry');

        /** @var list<array{code: string, fields: list<array{name: string, schema: array<string, mixed>}>}> $contentTypes */
        $contentTypes = $registry['contentTypes'] ?? [];
        $blogPost = array_values(array_filter($contentTypes, static fn (array $contentType): bool => 'blog_post' === $contentType['code']))[0] ?? null;
        self::assertNotNull($blogPost);

        $fields = array_column($blogPost['fields'], 'schema', 'name');
        self::assertSame(['publishedAt', 'featuredImage', 'categories', 'excerpt'], array_keys($fields));
        self::assertSame('entries', $fields['categories']['x-cms-type'] ?? null);
        self::assertSame(['max' => null, 'sources' => [], 'limit' => 8, 'contentType' => 'blog_category'], $fields['categories']['x-cms-options'] ?? null);
        self::assertSame('Date', $fields['publishedAt']['x-cms-label'] ?? null);
    }

    /**
     * @test
     */
    public function it_finds_only_the_blog_categories_published_or_not_newest_first(): void
    {
        $client = self::signedIn();
        $news = self::createBlogCategory('News', 'news', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes');
        self::createBlogPost('News from the workshop', 'news-from-the-workshop', [$news], published: true);
        self::createPage('News', 'news', published: true);

        self::assertSame(
            [['ref' => $recipes->getUuid(), 'name' => 'Recipes', 'thumbnail' => null], ['ref' => $news->getUuid(), 'name' => 'News', 'thumbnail' => null]],
            self::json($client, 'GET', '/admin/cms/api/search/entry?q=&contentType=blog_category'),
        );
        self::assertSame([$news->getUuid()], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/entry?q=NEW&contentType=blog_category'), 'ref'));
    }

    /**
     * FAQ categories have no URL of their own, so no link leads to them, but a FAQ item's categories picker finds them
     *
     * @test
     */
    public function it_tells_the_picker_of_a_faq_items_categories_to_search_the_faq_categories_which_it_finds(): void
    {
        $client = self::signedIn();
        $shipping = self::createFaqCategory('Shipping');
        self::createFaqItem('How fast do you ship?', 'Within three days.', [$shipping]);

        $registry = (array) self::json($client, 'GET', '/admin/cms/api/registry');
        /** @var list<array{code: string, prefix: string|null, blocks: mixed, fields: list<array{name: string, schema: array<string, mixed>}>}> $contentTypes */
        $contentTypes = $registry['contentTypes'] ?? [];
        $faqItem = array_values(array_filter($contentTypes, static fn (array $contentType): bool => 'faq_item' === $contentType['code']))[0] ?? null;
        self::assertNotNull($faqItem);
        self::assertNull($faqItem['prefix']);
        self::assertNull($faqItem['blocks']);

        $fields = array_column($faqItem['fields'], 'schema', 'name');
        self::assertSame(['categories', 'answer'], array_keys($fields));
        self::assertSame(['max' => null, 'sources' => [], 'limit' => 8, 'contentType' => 'faq_category'], $fields['categories']['x-cms-options'] ?? null);
        self::assertSame('rich_text', $fields['answer']['x-cms-type'] ?? null);
        self::assertTrue($fields['answer']['x-cms-translatable'] ?? null);

        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/entry?q=ship'), 'A link leads only to an entry with a URL of its own');
        self::assertSame(
            [['ref' => $shipping->getUuid(), 'name' => 'Shipping', 'thumbnail' => null]],
            self::json($client, 'GET', '/admin/cms/api/search/entry?q=ship&contentType=faq_category'),
        );
    }

    /**
     * A content type without a URL of its own can be searched by its code too, as FAQ categories are
     *
     * @test
     */
    public function it_finds_the_entries_of_a_content_type_without_a_url_of_its_own_when_it_is_named(): void
    {
        $client = self::signedIn();
        $announcement = self::createAnnouncement('Winter sale');

        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/entry?q=winter'), 'A link leads only to an entry with a URL of its own');
        self::assertSame([$announcement->getUuid()], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/entry?q=winter&contentType=announcement'), 'ref'));
        self::assertSame([$announcement->getUuid()], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/entry?refs=' . $announcement->getUuid()), 'ref'));
    }

    /**
     * @test
     */
    public function it_finds_nothing_of_a_content_type_the_code_does_not_have(): void
    {
        $client = self::signedIn();
        self::createBlogCategory('News', 'news');

        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/entry?q=&contentType=recipe'));
        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/entry?q=&contentType[]=blog_category'));
    }

    /**
     * The picker names what the field holds by looking it up, and flags what the lookup doesn't find
     *
     * @test
     */
    public function it_looks_up_the_categories_a_post_holds_without_a_deleted_one(): void
    {
        $client = self::signedIn();
        $news = self::createBlogCategory('News', 'news', published: true);
        $gone = self::createBlogCategory('Gone', 'gone', published: true);
        $goneUuid = $gone->getUuid();

        /** @var EntryDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EntryDeleterInterface::class);
        $deleter->delete($gone);

        self::assertSame(
            [['ref' => $news->getUuid(), 'name' => 'News', 'thumbnail' => null]],
            self::json($client, 'GET', sprintf('/admin/cms/api/search/entry?refs=%s,%s', $goneUuid, $news->getUuid())),
        );
    }
}
