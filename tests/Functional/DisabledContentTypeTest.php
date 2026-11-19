<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Query\NotVisibleReason;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocation;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\DisabledContentTypeKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A content type switched off in setono_sylius_cms.disabled.content_types is hidden from the admin, the builder, sources
 * and routing, while its columns stay mapped. See "What ships" in docs/spec/collections.md
 */
final class DisabledContentTypeTest extends WebTestCase
{
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_answers_404_at_the_url_of_an_entry_of_a_content_type_switched_off(): void
    {
        $client = self::createClient();
        self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', published: true);

        $client->request('GET', '/en_US/stores/aarhus');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_leaves_a_content_type_switched_off_out_of_the_content_hubs_menu_its_counts_and_its_lists(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C');
        self::createAnnouncement('Free shipping');

        $crawler = $client->request('GET', '/admin/cms/entries');

        self::assertResponseIsSuccessful();
        $items = $crawler->filter('[data-hub-menu] a.item')->each(static fn (Crawler $item): string => (string) preg_replace('/\s+/', ' ', trim($item->text())));
        self::assertSame(['Overview', 'All entries 1', 'Pages 0', 'Snippets 0', 'Blog 0', 'FAQ 0', 'Announcements 1', 'Media', 'Presets'], $items);
        self::assertSelectorTextNotContains('table', 'Aarhus');

        $client->request('GET', '/admin/cms/entries/store_location');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/cms/entries/store_location/new');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_hides_an_entry_of_a_content_type_switched_off_from_its_overview_page_and_the_builder(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $storeLocation = self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C');

        $client->request('GET', '/admin/cms/entry/' . $storeLocation->getUuid());
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $storeLocation->getUuid()));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/cms/api/entries/' . $storeLocation->getUuid(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/cms/api/registry', server: ['HTTP_ACCEPT' => 'application/json']);
        /** @var array{contentTypes: list<array{code: string}>} $registry */
        $registry = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['announcement', 'page', 'snippet', 'blog_post', 'blog_category', 'faq_item', 'faq_category'], array_column($registry['contentTypes'], 'code'));
    }

    /**
     * Sources, links and Entries fields read entries through the visible-entries query, which shows none of a content
     * type switched off, as if each were disabled
     *
     * @test
     */
    public function it_shows_no_entry_of_a_content_type_switched_off_to_sources_and_links(): void
    {
        self::bootKernel();
        $storeLocation = self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', published: true);
        /** @var VisibleEntriesQueryInterface $query */
        $query = self::getContainer()->get(VisibleEntriesQueryInterface::class);
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $context = new RenderContext($channel, 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());

        self::assertSame([], $query->createQueryBuilder('store_location', $context)->getQuery()->getResult());
        self::assertSame([$storeLocation->getUuid() => NotVisibleReason::Disabled], $query->whyNotVisible('store_location', [$storeLocation->getUuid()], $context));
    }

    /**
     * The schema never depends on the setting
     *
     * @test
     */
    public function it_keeps_the_columns_of_a_content_type_switched_off_mapped(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');

        self::assertSame('store_location_city', $manager->getClassMetadata(StoreLocation::class)->getColumnName('city'));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new DisabledContentTypeKernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    }
}
