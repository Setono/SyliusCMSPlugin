<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ContentHubTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function the_sylius_admin_menu_has_one_content_item_that_opens_the_hub(): void
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
    public function the_hub_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function the_hub_has_its_own_menu_with_the_overview_and_the_entry_lists_with_their_counts(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createPage('About us', 'about-us');
        self::createPage('Contact', 'contact');

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        $menu = $crawler->filter('[data-hub-menu]');
        self::assertSame(['Overview', 'Entries'], $menu->filter('.header, a.item[href="/admin/cms"]')->each(static fn (Crawler $item): string => self::label($item)));
        self::assertSame(
            [['All entries', '2', '/admin/cms/entries'], ['Pages', '2', '/admin/cms/entries/page']],
            $menu->filter('.item > .menu > a.item')->each(static fn (Crawler $item): array => [self::label($item), $item->filter('.label')->text(), (string) $item->attr('href')]),
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
