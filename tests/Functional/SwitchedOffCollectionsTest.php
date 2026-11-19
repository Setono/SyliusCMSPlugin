<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Entry\CollectionChoicesProvider;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SwitchedOffCollectionsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A project switches off a shipped collection as a whole, its entries' content type with its categories', in
 * setono_sylius_cms.disabled.content_types: the Blog and the FAQ leave the admin, the builder, sources and routing.
 * See "What ships" in docs/spec/collections.md
 */
final class SwitchedOffCollectionsTest extends WebTestCase
{
    use Blog;
    use Entries;
    use Faq;

    /**
     * @test
     */
    public function it_leaves_the_blog_and_the_faq_out_of_the_content_hubs_menu_and_answers_404_for_their_lists(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $crawler = $client->request('GET', '/admin/cms/entries');

        self::assertResponseIsSuccessful();
        $items = $crawler->filter('[data-hub-menu] a.item')->each(static fn (Crawler $item): string => (string) preg_replace('/\s+/', ' ', trim($item->text())));
        self::assertSame(['Overview', 'All entries 0', 'Pages 0', 'Snippets 0', 'Announcements 0', 'Store locations 0', 'Media', 'Presets', 'Transfer'], $items);

        foreach (['blog_post', 'blog_category', 'faq_item', 'faq_category'] as $contentType) {
            $client->request('GET', '/admin/cms/entries/' . $contentType);
            self::assertResponseStatusCodeSame(404, $contentType);
        }
    }

    /**
     * @test
     */
    public function it_answers_404_at_the_urls_of_blog_posts_and_categories(): void
    {
        $client = self::createClient();
        $category = self::createBlogCategory('News', 'news', published: true);
        self::createBlogPost('Hello', 'hello', [$category], published: true);

        $client->request('GET', '/en_US/blog/hello');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/blog/category/news');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_shows_no_entry_of_the_blog_or_the_faq_to_sources_and_offers_neither_to_from_a_collection(): void
    {
        self::bootKernel();
        $category = self::createFaqCategory('Shipping', published: true);
        self::createFaqItem('How long does shipping take?', 'Two days', [$category], published: true);
        self::createBlogPost('Hello', 'hello', published: true);
        /** @var VisibleEntriesQueryInterface $query */
        $query = self::getContainer()->get(VisibleEntriesQueryInterface::class);
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $context = new RenderContext($channel, 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());

        foreach (['blog_post', 'blog_category', 'faq_item', 'faq_category'] as $contentType) {
            self::assertSame([], $query->createQueryBuilder($contentType, $context)->getQuery()->getResult(), $contentType);
        }

        /** @var CollectionChoicesProvider $choices */
        $choices = self::getContainer()->get(CollectionChoicesProvider::class);
        self::assertSame(['announcement', 'store_location'], array_keys($choices->getChoices('en_US')));
    }

    /**
     * @test
     */
    public function it_leaves_the_blog_and_the_faq_out_of_the_builders_registry(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/api/registry', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        /** @var array{contentTypes: list<array{code: string}>} $registry */
        $registry = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['announcement', 'store_location', 'page', 'snippet'], array_column($registry['contentTypes'], 'code'));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new SwitchedOffCollectionsKernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    }
}
