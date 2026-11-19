<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Source;

use Pagerfanta\Adapter\AdapterInterface;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Entry\EntriesFromCollectionParams;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The entry sources against a real database: what each lists, in its order, ending in the ID, counted and paged in
 * SQL, with the category filters on the reference table. See "Entry sources" in docs/spec/sources.md
 */
final class EntrySourcesTest extends KernelTestCase
{
    use Blog;
    use Faq;

    /**
     * @test
     */
    public function it_counts_and_pages_from_a_collection_in_categories_each_post_once_without_overlapping_or_skipping(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        $guides = self::createBlogCategory('Guides', 'guides', published: true);
        foreach (['First' => [$news], 'Second' => [$news, $guides], 'Third' => [$guides], 'Fourth' => [], 'Fifth' => [$guides, $news]] as $title => $categories) {
            self::createBlogPost($title, strtolower($title), $categories, published: true, publishedAt: new \DateTimeImmutable('2026-06-01'));
        }
        self::createBlogPost('Unpublished', 'unpublished', [$news]);

        $adapter = self::fromCollection('blog_post', [$news, $guides]);

        self::assertSame(4, $adapter->getNbResults());
        // Posts of one date tie, so they're listed by ID
        self::assertSame(['First', 'Second'], self::titles($adapter->getSlice(0, 2)));
        self::assertSame(['Third', 'Fifth'], self::titles($adapter->getSlice(2, 2)));
    }

    /**
     * @test
     */
    public function it_lists_from_a_collection_every_visible_entry_in_the_content_type_s_order(): void
    {
        self::createFaqItem('Second question', published: true);
        self::createFaqItem('Unpublished question');
        self::createFaqItem('Third question', published: true);

        self::assertSame(['Second question', 'Third question'], self::titles(self::fromCollection('faq_item')->getSlice(0, 10)));
    }

    /**
     * @test
     */
    public function it_lists_related_entries_newest_first_by_their_date_leaving_out_the_entry(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        self::createBlogPost('Old', 'old', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-01-01'));
        self::createBlogPost('New', 'new', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-09-01'));
        $post = self::createBlogPost('This post', 'this-post', [$news], published: true, publishedAt: new \DateTimeImmutable('2026-12-01'));

        $adapter = self::source('related_entries')->resolve(new NoParams(), self::context($post));

        self::assertSame(['New', 'Old'], self::titles($adapter->getSlice(0, 10)));
        self::assertSame(2, $adapter->getNbResults());
    }

    /**
     * @param list<EntryInterface>|null $categories
     *
     * @return AdapterInterface<mixed>
     */
    private static function fromCollection(string $contentType, ?array $categories = null): AdapterInterface
    {
        $params = new EntriesFromCollectionParams();
        $params->contentType = $contentType;
        $params->categories = null === $categories ? null : ItemList::of($categories);

        return self::source('entries_from_collection')->resolve($params, self::context());
    }

    private static function source(string $code): SourceInterface
    {
        $registry = self::getContainer()->get(SourceRegistryInterface::class);
        self::assertInstanceOf(SourceRegistryInterface::class, $registry);

        return $registry->get($code)->source;
    }

    /**
     * @param iterable<mixed> $entries
     *
     * @return list<string|null>
     */
    private static function titles(iterable $entries): array
    {
        $titles = [];
        foreach ($entries as $entry) {
            self::assertInstanceOf(EntryInterface::class, $entry);
            $titles[] = $entry->getTranslation('en_US')->getTitle();
        }

        return $titles;
    }

    private static function context(?EntryInterface $entry = null): RenderContext
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return Rendering::context(channel: $channel, hostPageContext: null === $entry ? [] : ['entry' => $entry]);
    }
}
