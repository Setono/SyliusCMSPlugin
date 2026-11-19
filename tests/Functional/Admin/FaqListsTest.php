<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The hub's FAQ item lists the FAQ's items and its categories, switched by All, Questions and Categories, and items and
 * categories are each dragged into order in their own list. See "In the admin" in docs/spec/collections.md and "Entry
 * lists" in docs/spec/admin.md
 */
final class FaqListsTest extends WebTestCase
{
    use Entries;
    use Faq;

    /**
     * @test
     */
    public function it_lists_the_faqs_items_and_categories_together_from_the_faq_item(): void
    {
        $client = self::signedIn();
        $shipping = self::createFaqCategory('Shipping', published: true);
        $item = self::createFaqItem('How fast do you ship?', 'Within three days.', [$shipping]);

        $crawler = $client->request('GET', '/admin/cms');
        $faq = $crawler->filter('[data-hub-menu] a.item')->reduce(static fn (Crawler $item): bool => '/admin/cms/collections/faq_item' === $item->attr('href'));
        self::assertCount(1, $faq);
        self::assertSame('FAQ', trim(substr($faq->text(), 0, -strlen($faq->filter('.label')->text()))));
        self::assertSame('2', $faq->filter('.label')->text());

        $crawler = $client->click($faq->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'FAQ');
        self::assertEqualsCanonicalizing(
            [[$item->getUuid(), 'FAQ item'], [$shipping->getUuid(), 'FAQ category']],
            $crawler->filter('table tbody tr')->each(static fn (Crawler $row): array => [(string) $row->filter('[data-entry]')->attr('data-entry'), trim($row->filter('[data-column="type"]')->text())]),
        );
        self::assertSame(
            [['All', '/admin/cms/collections/faq_item', true], ['Questions', '/admin/cms/entries/faq_item', false], ['Categories', '/admin/cms/entries/faq_category', false]],
            self::switches($crawler),
        );
        // All mixes two content types, so it's not where they're dragged into order
        self::assertCount(0, $crawler->filter('[data-cms-entry-order]'));
        self::assertSame(
            [['FAQ item', '/admin/cms/entries/faq_item/new'], ['FAQ category', '/admin/cms/entries/faq_category/new']],
            $crawler->filter('dialog#cms-create-entry [data-cms-create-entry-type]')->each(static fn (Crawler $type): array => [trim($type->text()), (string) $type->attr('href')]),
        );
    }

    /**
     * @test
     */
    public function it_switches_to_the_items_and_to_the_categories_each_dragged_into_order_in_its_list(): void
    {
        $client = self::signedIn();
        $shipping = self::createFaqCategory('Shipping');
        $item = self::createFaqItem('How fast do you ship?', categories: [$shipping]);

        $crawler = $client->request('GET', '/admin/cms/collections/faq_item');
        $crawler = $client->click($crawler->filter('[data-cms-collection-switch] a')->selectLink('Questions')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'FAQ');
        self::assertSame([$item->getUuid()], self::listedUuids($crawler));
        self::assertSame([['All', '/admin/cms/collections/faq_item', false], ['Questions', '/admin/cms/entries/faq_item', true], ['Categories', '/admin/cms/entries/faq_category', false]], self::switches($crawler));
        self::assertSame('/admin/cms/entries/faq_item/order', $crawler->filter('[data-cms-entry-order]')->attr('data-url'));

        $crawler = $client->click($crawler->filter('[data-cms-collection-switch] a')->selectLink('Categories')->link());

        self::assertResponseIsSuccessful();
        self::assertSame([$shipping->getUuid()], self::listedUuids($crawler));
        self::assertSame([['All', '/admin/cms/collections/faq_item', false], ['Questions', '/admin/cms/entries/faq_item', false], ['Categories', '/admin/cms/entries/faq_category', true]], self::switches($crawler));
        self::assertSame('/admin/cms/entries/faq_category/order', $crawler->filter('[data-cms-entry-order]')->attr('data-url'));
        self::assertSame('/admin/cms/entries/faq_category/new', $crawler->filter('[data-cms-create-entry]')->attr('href'));
    }

    private static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * Each switch's name, link and whether it's the list shown
     *
     * @return array<mixed>
     */
    private static function switches(Crawler $crawler): array
    {
        return $crawler->filter('[data-cms-collection-switch] a')->each(static fn (Crawler $link): array => [
            trim($link->text()),
            (string) $link->attr('href'),
            'page' === $link->attr('aria-current'),
        ]);
    }

    /**
     * @return array<mixed>
     */
    private static function listedUuids(Crawler $crawler): array
    {
        return $crawler->filter('table tbody [data-entry]')->each(static fn (Crawler $title): string => (string) $title->attr('data-entry'));
    }
}
