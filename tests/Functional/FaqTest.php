<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\FaqItemInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The FAQ's items and categories: no URL of their own, ordered by the position editors drag them into, and a published
 * item's categories in the reference table. See "Shipped content types" and "Categories" in docs/spec/collections.md
 */
final class FaqTest extends WebTestCase
{
    use Entries;
    use Faq;

    /**
     * @test
     */
    public function it_gives_faq_items_and_faq_categories_no_url_of_their_own(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $shipping = self::createFaqCategory('Shipping', published: true);
        $item = self::createFaqItem('How fast do you ship?', 'Within three days.', [$shipping], published: true);

        /** @var CanonicalUrlGeneratorInterface $canonicalUrls */
        $canonicalUrls = self::getContainer()->get(CanonicalUrlGeneratorInterface::class);
        self::assertNull($canonicalUrls->generate($item, self::channel(), 'en_US'));
        self::assertNull($canonicalUrls->generate($shipping, self::channel(), 'en_US'));

        // Their create forms ask for no slug
        foreach (['faq_item', 'faq_category'] as $contentType) {
            $crawler = $client->request('GET', sprintf('/admin/cms/entries/%s/new', $contentType));
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('input[name="setono_sylius_cms_create_entry[title]"]'));
            self::assertCount(0, $crawler->filter('input[name="setono_sylius_cms_create_entry[slug]"]'), sprintf('A %s has no slug', $contentType));
        }
    }

    /**
     * @test
     */
    public function it_creates_a_faq_item_without_a_slug_or_a_layout_and_opens_the_builder(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $crawler = $client->request('GET', '/admin/cms/entries/faq_item/new');
        $client->submit($crawler->selectButton('Create')->form(['setono_sylius_cms_create_entry[title]' => 'How fast do you ship?']));

        self::assertResponseRedirects();
        /** @var EntryRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.entry');
        $item = $repository->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(FaqItemInterface::class, $item);
        self::assertSame('How fast do you ship?', $item->getTranslation('en_US')->getTitle());
        self::assertNull($item->getTranslation('en_US')->getSlug());
        self::assertNull($item->getBlockTree()->layout);
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s/builder', $item->getUuid()), (string) $client->getResponse()->headers->get('Location'));
    }

    /**
     * A new item goes last, the admin list shows them in their order, and the order editors drag them into is the one
     * they're listed in, as the entry sources will list them
     *
     * @test
     */
    public function it_orders_faq_items_by_the_position_editors_drag_them_into(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $first = self::createFaqItem('How fast do you ship?');
        $second = self::createFaqItem('Can I return an item?');
        $third = self::createFaqItem('Do you ship abroad?');

        self::assertSame([0, 1, 2], [$first->getPosition(), $second->getPosition(), $third->getPosition()]);
        $crawler = $client->request('GET', '/admin/cms/entries/faq_item');
        self::assertSame(['How fast do you ship?', 'Can I return an item?', 'Do you ship abroad?'], self::listed($crawler));
        self::assertSelectorExists('[data-cms-entry-order][data-url="/admin/cms/entries/faq_item/order"]');

        $client->request('POST', '/admin/cms/entries/faq_item/order', [
            '_csrf_token' => (string) $crawler->filter('[data-cms-entry-order]')->attr('data-csrf-token'),
            'entries' => [$third->getUuid(), $first->getUuid(), $second->getUuid()],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Do you ship abroad?', 'How fast do you ship?', 'Can I return an item?'], self::listed($client->request('GET', '/admin/cms/entries/faq_item')));
        self::assertSame(['Do you ship abroad?', 'How fast do you ship?', 'Can I return an item?'], self::inContentTypeOrder('faq_item'));
    }

    /**
     * @test
     */
    public function it_orders_faq_categories_by_the_position_editors_drag_them_into(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $shipping = self::createFaqCategory('Shipping');
        $returns = self::createFaqCategory('Returns');

        self::assertSame([0, 1], [$shipping->getPosition(), $returns->getPosition()]);
        $crawler = $client->request('GET', '/admin/cms/entries/faq_category');
        self::assertSame(['Shipping', 'Returns'], self::listed($crawler));

        $client->request('POST', '/admin/cms/entries/faq_category/order', [
            '_csrf_token' => (string) $crawler->filter('[data-cms-entry-order]')->attr('data-csrf-token'),
            'entries' => [$returns->getUuid(), $shipping->getUuid()],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Returns', 'Shipping'], self::inContentTypeOrder('faq_category'));
    }

    /**
     * @test
     */
    public function it_writes_the_categories_of_a_faq_item_it_publishes_to_the_reference_table(): void
    {
        $shipping = self::createFaqCategory('Shipping', published: true);
        $returns = self::createFaqCategory('Returns', published: true);
        $item = self::createFaqItem('Can I return an item I had shipped?', 'Within 30 days.', [$returns, $shipping]);
        self::assertSame([], self::referencesOf($item), 'An item that was never published has no rows');

        self::publishEntry($item);

        self::assertSame(self::sorted([['categories', $shipping->getUuid()], ['categories', $returns->getUuid()]]), self::referencesOf($item));

        // A draft's categories live in the draft until it's published
        self::saveChanges($item, [['op' => 'set_entry_value', 'field' => 'categories', 'locale' => null, 'value' => [$returns->getUuid()]]]);
        self::assertSame(self::sorted([['categories', $shipping->getUuid()], ['categories', $returns->getUuid()]]), self::referencesOf($item));

        self::publishEntry($item);

        self::assertSame([['categories', $returns->getUuid()]], self::referencesOf($item));
    }

    /**
     * The answer is drafted and published per translation, as rich text
     *
     * @test
     */
    public function it_publishes_the_answer_an_editor_drafts(): void
    {
        $item = self::createFaqItem('How fast do you ship?');

        self::saveChanges($item, [['op' => 'set_entry_value', 'field' => 'answer', 'locale' => 'en_US', 'value' => self::answer('Within three days.')]]);
        self::publishEntry($item);

        self::entityManager()->clear();
        $reloaded = self::entityManager()->find($item::class, $item->getId());
        self::assertInstanceOf(FaqItemInterface::class, $reloaded);
        // MySQL keeps a JSON object's keys in an order of its own
        self::assertEquals(self::answer('Within three days.'), $reloaded->getTranslation('en_US')->getAnswer());
    }

    /**
     * @return list<string> the titles of the admin list's rows, in order
     */
    private static function listed(Crawler $crawler): array
    {
        /** @var list<string> $titles */
        $titles = $crawler->filter('[data-column="title"] strong')->each(static fn (Crawler $title): string => trim($title->text()));

        return $titles;
    }

    /**
     * The titles of a content type's entries in the order its metadata gives, which sources list them in
     *
     * @return list<string>
     */
    private static function inContentTypeOrder(string $contentType): array
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager->clear();

        /** @var ContentTypeRegistryInterface $contentTypes */
        $contentTypes = self::getContainer()->get(ContentTypeRegistryInterface::class);
        $metadata = $contentTypes->get($contentType);

        $queryBuilder = self::entityManager()->createQueryBuilder()->select('entry')->from($metadata->modelClass, 'entry');
        $metadata->order->applyTo($queryBuilder, 'entry');

        /** @var list<EntryInterface> $entries */
        $entries = $queryBuilder->getQuery()->getResult();

        return array_map(static fn (EntryInterface $entry): string => (string) $entry->getTranslation('en_US')->getTitle(), $entries);
    }

    private static function channel(): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    /**
     * @param list<array{string, string}> $references
     *
     * @return list<array{string, string}>
     */
    private static function sorted(array $references): array
    {
        sort($references);

        return $references;
    }
}
