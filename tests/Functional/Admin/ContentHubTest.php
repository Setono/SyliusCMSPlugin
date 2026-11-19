<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ContentHubTest extends WebTestCase
{
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_adds_one_content_item_that_opens_the_hub_to_the_sylius_admin_menu(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $crawler = $client->request('GET', '/admin/');

        $items = $crawler->filter('.sylius-admin-menu a.item')->reduce(static fn (Crawler $item): bool => str_starts_with((string) $item->attr('href'), '/admin/cms'));
        self::assertCount(1, $items);
        self::assertSame('Content', trim($items->text()));
        self::assertSame('/admin/cms', $items->attr('href'));

        $client->click($items->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Content');
        self::assertSelectorTextContains('.sylius-admin-menu a.item.active', 'Content');
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user_for_the_hub(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_gives_the_hub_its_own_menu_with_the_overview_the_entry_lists_with_their_counts_and_the_library(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createPage('About us', 'about-us');
        self::createPage('Contact', 'contact');
        self::createStoreLocation('Aarhus', 'aarhus');

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        $menu = $crawler->filter('[data-hub-menu]');
        self::assertSame(['Overview', 'Entries', 'Library'], $menu->filter('.header, a.item[href="/admin/cms"]')->each(static fn (Crawler $item): string => self::label($item)));
        self::assertSame(
            [
                ['All entries', '3', '/admin/cms/entries'],
                ['Pages', '2', '/admin/cms/entries/page'],
                ['Snippets', '0', '/admin/cms/entries/snippet'],
                // An item per collection: the shipped blog, named by its name, which lists its posts and categories,
                // then the test application's own content types, named by their plurals
                ['Blog', '0', '/admin/cms/collections/blog_post'],
                ['FAQ', '0', '/admin/cms/collections/faq_item'],
                ['Announcements', '0', '/admin/cms/entries/announcement'],
                ['Store locations', '1', '/admin/cms/entries/store_location'],
                ['Media', null, '/admin/cms/media'],
            ],
            $menu->filter('.item > .menu > a.item')->each(static fn (Crawler $item): array => [self::label($item), 0 === $item->filter('.label')->count() ? null : $item->filter('.label')->text(), (string) $item->attr('href')]),
        );
        self::assertSame('Pages', self::label($menu->filter('a.item.active')));
    }

    /**
     * The text of a menu item without its count
     */
    private static function label(Crawler $item): string
    {
        $count = $item->filter('.label');

        return trim(substr($item->text(), 0, 0 === $count->count() ? null : -strlen($count->text())));
    }
}
