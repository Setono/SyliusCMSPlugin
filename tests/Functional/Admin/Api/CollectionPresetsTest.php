<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Problem\Kind\CategoryArchiveWithoutEntries;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Blog's and the FAQ's starting points and block presets: the blog page and the FAQ page for pages, each category's
 * archive, which creating a blog category preselects, the latest posts anywhere and the related posts on blog posts. A
 * category whose tree no longer holds an Entries block is a warning problem. See "Shipped layouts, starting points and
 * presets" and "Listings" in docs/spec/collections.md, and "Problems" in docs/spec/admin.md
 */
final class CollectionPresetsTest extends BuilderApiTestCase
{
    use Blog;
    use Faq;

    /**
     * @test
     */
    public function creating_a_blog_category_preselects_the_category_archive(): void
    {
        $client = self::signedIn();

        $crawler = $client->request('GET', '/admin/cms/entries/blog_category/new');

        self::assertResponseIsSuccessful();
        $cards = $crawler->filter('fieldset[data-cms-create-entry-starting-points] .cms-starting-point-card');
        self::assertSame(['Blank', 'Category archive'], $cards->each(static fn (Crawler $card): string => $card->filter('.cms-layout-card__label')->text()));
        self::assertSame('/bundles/setonosyliuscmsplugin/thumbnail/preset/blog_category_archive.svg', $cards->eq(1)->filter('img')->attr('src'));
        self::assertSame(['blog_category_archive'], $crawler->filter('input[name="setono_sylius_cms_create_entry[startingPoint]"]:checked')->each(static fn (Crawler $radio): string => (string) $radio->attr('value')));
    }

    /**
     * @test
     */
    public function a_blog_post_starts_from_blank_as_it_has_no_starting_point(): void
    {
        $client = self::signedIn();

        $crawler = $client->request('GET', '/admin/cms/entries/blog_post/new');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('fieldset[data-cms-create-entry-starting-points]'));
    }

    /**
     * @test
     */
    public function a_category_created_from_its_default_starting_point_lists_its_posts_at_its_url(): void
    {
        $client = self::signedIn();
        $news = self::createCategoryInDialog($client, 'News', 'news');
        self::publish($client, $news);
        self::createPosts(10, [$news]);
        self::createBlogPost('Elsewhere', 'elsewhere', published: true);

        $crawler = $client->request('GET', '/en_US/blog/category/news');

        self::assertResponseIsSuccessful();
        self::assertSame(['Post 10', 'Post 9', 'Post 8', 'Post 7', 'Post 6', 'Post 5', 'Post 4', 'Post 3', 'Post 2'], self::titles($crawler));
        self::assertSame('/en_US/blog/category/news?page=2', $crawler->filter('a.cms-pagination__next')->attr('href'));

        $crawler = $client->request('GET', '/en_US/blog/category/news?page=2');
        self::assertResponseIsSuccessful();
        self::assertSame(['Post 1'], self::titles($crawler));
    }

    /**
     * @test
     */
    public function removing_the_entries_block_from_a_category_s_tree_raises_a_warning(): void
    {
        $client = self::signedIn();
        $news = self::createCategoryInDialog($client, 'News', 'news');
        self::assertEquals(new ProblemCount(), self::storedCount($news));

        self::saveChanges($news, [['op' => 'remove_block', 'key' => $news->getBlockTree()->blocks[0]->key]]);

        self::assertEquals(new ProblemCount(0, 1), self::storedCount($news));
        $problems = [...self::problemFinder()->findByEntry($news)];
        self::assertCount(1, $problems);
        self::assertSame(CategoryArchiveWithoutEntries::CODE, $problems[0]->kind);
        self::assertSame(Severity::Warning, $problems[0]->severity);
        self::assertSame('Its page lists none of the category\'s entries, as no Entries block is left in its blocks. Add an Entries block filled by In this category.', $problems[0]->message->trans(self::translator()));
    }

    /**
     * @test
     */
    public function the_blog_page_lists_the_blog_s_posts_split_into_pages_under_its_heading(): void
    {
        $client = self::signedIn();
        $blog = self::createPageInDialog($client, 'Blog', 'blog', 'blog_page');
        self::publish($client, $blog);
        self::createPosts(10);

        $crawler = $client->request('GET', '/en_US/blog');

        self::assertResponseIsSuccessful();
        self::assertSame('Latest posts', $crawler->filter('h2.cms-heading')->text());
        self::assertCount(9, $crawler->filter('.cms-entries--grid .cms-blog-post-card'));
        self::assertSame('/en_US/blog?page=2', $crawler->filter('a.cms-pagination__next')->attr('href'));
    }

    /**
     * @test
     */
    public function the_faq_page_lists_the_faq_s_questions_in_an_accordion_under_its_heading(): void
    {
        $client = self::signedIn();
        $faq = self::createPageInDialog($client, 'Help', 'help', 'faq_page');
        self::publish($client, $faq);
        self::createFaqItem('How long does delivery take?', 'Two to five days.', published: true);
        self::createFaqItem('Can I return my order?', 'Within 30 days.', published: true);

        $crawler = $client->request('GET', '/en_US/help');

        self::assertResponseIsSuccessful();
        self::assertSame('Frequently asked questions', $crawler->filter('h2.cms-heading')->text());
        self::assertSame(
            ['How long does delivery take?', 'Can I return my order?'],
            $crawler->filter('.cms-entries--accordion details.cms-accordion-item > summary')->each(static fn (Crawler $summary): string => $summary->text()),
        );
    }

    /**
     * @test
     */
    public function related_posts_is_offered_only_in_blog_posts_and_latest_posts_anywhere(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');
        $category = self::createBlogCategory('News', 'news');
        $post = self::createBlogPost('Opening night', 'opening-night');
        $snippet = self::createSnippet('Footer');

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $post->getUuid()));
        self::assertTrue($presets['related_posts']['fits']);
        self::assertTrue($presets['latest_posts']['fits']);
        self::assertSame(['Related posts', 'Blog'], [$presets['related_posts']['name'], $presets['related_posts']['category']]);

        foreach ([$page, $category, $snippet] as $entry) {
            $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $entry->getUuid()));
            self::assertFalse($presets['related_posts']['fits'], $entry->getTitle() ?? '');
            self::assertNotSame([], $presets['related_posts']['reasons']);
            self::assertTrue($presets['latest_posts']['fits'], $entry->getTitle() ?? '');
        }
    }

    /**
     * @test
     */
    public function the_latest_posts_and_the_related_posts_publish_in_a_blog_post_and_list_its_neighbours(): void
    {
        $client = self::signedIn();
        $news = self::createBlogCategory('News', 'news', published: true);
        self::createPosts(4, [$news]);
        self::createBlogPost('Elsewhere', 'elsewhere', published: true, publishedAt: new \DateTimeImmutable('2025-01-01'));
        $post = self::createBlogPost('Opening night', 'opening-night', [$news], published: true, publishedAt: new \DateTimeImmutable('2024-01-01'));
        $csrfToken = self::openBuilder($client, $post);

        $latest = self::insert($client, 'latest_posts', $post);
        $related = self::insert($client, 'related_posts', $post);
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $post->getUuid());
        self::assertIsArray($response);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $post->getUuid(), ['lockToken' => $response['lockToken'] ?? null, 'changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => $latest],
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => $related],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();
        self::publish($client, $post, $csrfToken);

        $lists = $client->request('GET', '/en_US/blog/opening-night')->filter('.cms-entries');

        self::assertResponseIsSuccessful();
        self::assertSame(['Post 4', 'Post 3', 'Post 2'], self::titles($lists->eq(0)), 'The three newest posts');
        self::assertSame(['Post 4', 'Post 3', 'Post 2'], self::titles($lists->eq(1)), 'The newest three of the others in its categories');
    }

    private static function createCategoryInDialog(KernelBrowser $client, string $title, string $slug): BlogCategoryInterface
    {
        $client->request('GET', '/admin/cms/entries/blog_category/new');
        // The category archive is preselected
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => $title,
            'setono_sylius_cms_create_entry[slug]' => $slug,
        ]);

        /** @var RepositoryInterface<BlogCategoryInterface> $categories */
        $categories = self::getContainer()->get('setono_sylius_cms.repository.blog_category');

        return self::entryWithSlug($categories->findAll(), $slug);
    }

    private static function createPageInDialog(KernelBrowser $client, string $title, string $slug, string $startingPoint): PageInterface
    {
        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => $title,
            'setono_sylius_cms_create_entry[slug]' => $slug,
            'setono_sylius_cms_create_entry[startingPoint]' => $startingPoint,
        ]);

        /** @var RepositoryInterface<PageInterface> $pages */
        $pages = self::getContainer()->get('setono_sylius_cms.repository.page');

        return self::entryWithSlug($pages->findAll(), $slug);
    }

    /**
     * @template T of EntryInterface
     *
     * @param array<T> $entries
     *
     * @return T
     */
    private static function entryWithSlug(array $entries, string $slug): EntryInterface
    {
        foreach ($entries as $entry) {
            if ($entry->getTranslation('en_US')->getSlug() === $slug) {
                return $entry;
            }
        }

        self::fail(sprintf('There is no entry with the slug "%s"', $slug));
    }

    private static function publish(KernelBrowser $client, EntryInterface $entry, ?string $csrfToken = null): void
    {
        $csrfToken ??= self::openBuilder($client, $entry);
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $entry->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful((string) json_encode($response));
    }

    /**
     * The preset's blocks as preset_insert answers them, ready to insert into the entry
     *
     * @return array<string, mixed> its one block
     */
    private static function insert(KernelBrowser $client, string $code, EntryInterface $entry): array
    {
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/presets/%s/insert', $code), ['entry' => $entry->getUuid()]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame([], $response['notices'] ?? null);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertCount(1, $response['blocks']);
        self::assertIsArray($response['blocks'][0]);

        /** @var array<string, mixed> $block */
        $block = $response['blocks'][0];

        return $block;
    }

    /**
     * $count published posts, Post 1 the oldest
     *
     * @param list<BlogCategoryInterface> $categories
     */
    private static function createPosts(int $count, array $categories = []): void
    {
        for ($number = 1; $number <= $count; ++$number) {
            self::createBlogPost(sprintf('Post %d', $number), sprintf('post-%d', $number), $categories, published: true, publishedAt: new \DateTimeImmutable(sprintf('2026-01-01 +%d days', $number)));
        }
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

    private static function problemFinder(): ProblemFinderInterface
    {
        /** @var ProblemFinderInterface $finder */
        $finder = self::getContainer()->get(ProblemFinderInterface::class);

        return $finder;
    }

    private static function translator(): TranslatorInterface
    {
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get('translator');

        return $translator;
    }
}
