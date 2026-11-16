<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ShareLinks;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Share draft in the entry overview page's header, and its Share links section: each link's channel, locale, Shown to,
 * Date and who made it, with copy and revoke, plus New. See "Entry overview page" in docs/spec/admin.md and
 * "Share links" in docs/spec/publishing.md
 */
final class EntryShareLinksTest extends WebTestCase
{
    use Customers;
    use Entries;
    use ShareLinks;

    /**
     * @test
     */
    public function it_makes_a_share_link_for_the_channel_and_locale_picked_in_share_draft_and_comes_back_to_the_overview_page(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $crawler = $client->request('GET', $overview);
        $header = $crawler->filter('[data-overview-header]');
        self::assertSame('cms-share-draft', $header->filter('button[data-cms-dialog-open]')->attr('aria-controls'));
        self::assertStringContainsString('Share draft', $header->filter('button[data-cms-dialog-open]')->text());
        self::assertSame(['WEB|en_US' => 'Setono CMS Test Store · English (United States)'], self::targets($crawler));

        $client->submitForm('Create link', ['target' => 'WEB|en_US']);

        self::assertResponseRedirects($overview);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The share link has been made. Copy it from Share links.');

        $shareLinks = self::shareLinksOf($page);
        self::assertCount(1, $shareLinks);
        self::assertSame('John Doe', $shareLinks[0]->getCreatedBy());
        self::assertSame([[
            'Setono CMS Test Store',
            'English (United States)',
            'Guest',
            'Now',
            'John Doe',
            'http://localhost/en_US/about-us?_cms_share=' . $shareLinks[0]->getToken(),
        ]], self::rows($crawler));
    }

    /**
     * @test
     */
    public function it_lists_the_share_links_newest_first_with_their_shown_to_and_date(): void
    {
        $client = self::signedInClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('Prices', 'prices', 'Hello');
        self::addDanish($page);
        $english = self::createShareLink($page);
        self::channelsAndAudience()->setAudience($page, Audience::only('wholesale'));
        $page->setDateWindow(new DateWindow(new \DateTimeImmutable('2099-12-01T09:30:00Z'), null));
        self::flush();
        $danish = self::createShareLink($page, locale: 'da_DK');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $section = $crawler->filter('[data-section="share_links"]');
        self::assertSame('Share links', $section->filter('h2')->text());
        self::assertSame([
            ['Setono CMS Test Store', 'Danish (Denmark)', 'Wholesale', '2099-12-01 09:30', 'John Doe', 'http://localhost/da_DK/priser?_cms_share=' . $danish->getToken()],
            ['Setono CMS Test Store', 'English (United States)', 'Guest', 'Now', 'John Doe', 'http://localhost/en_US/prices?_cms_share=' . $english->getToken()],
        ], self::rows($crawler));
        self::assertCount(2, $section->selectButton('Revoke'));
        self::assertCount(2, $section->filter('button[data-cms-copy]'));
        self::assertCount(1, $section->filter('button[data-cms-dialog-open][aria-controls="cms-share-draft"]'), 'New opens the Share draft dialog');
    }

    /**
     * @test
     */
    public function it_says_there_are_none_before_the_first(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $section = $crawler->filter('[data-section="share_links"]');
        self::assertStringContainsString('Share the draft with anyone', $section->text());
        self::assertCount(0, $section->filter('[data-share-link]'));
    }

    /**
     * Share draft offers the channels and locales the entry's draft has a URL in
     *
     * @test
     */
    public function it_offers_share_draft_only_where_the_draft_has_a_url(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK', 'de_DE');
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::addDanish($page);
        self::createChannel('B2B', 'Wholesale store');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertSame([
            'WEB|en_US' => 'Setono CMS Test Store · English (United States)',
            'WEB|da_DK' => 'Setono CMS Test Store · Danish (Denmark)',
        ], self::targets($crawler));
    }

    /**
     * @test
     */
    public function it_says_why_in_share_draft_when_the_draft_has_no_url_to_share(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: []);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertSame([], self::targets($crawler));
        self::assertStringContainsString('It has no URL to share yet', $crawler->filter('#cms-share-draft')->text());
        self::assertCount(0, $crawler->filter('#cms-share-draft')->selectButton('Create link'));
    }

    /**
     * @test
     */
    public function it_says_why_share_draft_made_no_share_link(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());
        $crawler = $client->request('GET', $overview);
        $token = $crawler->filter('#cms-share-draft input[name="_csrf_token"]')->attr('value');

        $client->request('POST', $overview . '/share-links', ['_csrf_token' => $token, 'target' => 'WEB|da_DK']);
        self::assertResponseRedirects($overview);
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The channel WEB has no locale da_DK');

        $client->request('POST', $overview . '/share-links', ['_csrf_token' => $token]);
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'Pick a channel and a language to share the draft in.');

        self::assertSame([], self::shareLinksOf($page));
    }

    /**
     * @test
     */
    public function it_deletes_a_revoked_share_link_so_its_token_answers_as_for_a_shopper(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $token = self::createShareLink($page)->getToken();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Revoke');

        self::assertResponseRedirects($overview);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The share link has been revoked. It now answers as it would for anyone else.');
        self::assertSame([], self::rows($crawler));
        self::assertSame([], self::shareLinksOf($page));

        $client->request('GET', '/en_US/about-us?_cms_share=' . $token);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_needs_the_pages_csrf_token_and_a_share_link_of_the_entry_to_share_the_draft_and_revoke(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $other = self::createPage('Contact', 'contact', 'Hi');
        $shareLink = self::createShareLink($page);
        $ofOther = self::createShareLink($other);
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('POST', $overview . '/share-links', ['_csrf_token' => 'forged', 'target' => 'WEB|en_US']);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', sprintf('%s/share-links/%d/revoke', $overview, (int) $shareLink->getId()), ['_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $client->request('GET', $overview);
        $token = $crawler->filter('[data-section="share_links"] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', sprintf('%s/share-links/%d/revoke', $overview, (int) $ofOther->getId()), ['_csrf_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        self::assertCount(1, self::shareLinksOf($page));
        self::assertCount(1, self::shareLinksOf($other));
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user_to_share_the_draft_and_revoke(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);

        $client->request('POST', sprintf('/admin/cms/entry/%s/share-links', $page->getUuid()), ['target' => 'WEB|en_US']);
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('POST', sprintf('/admin/cms/entry/%s/share-links/%d/revoke', $page->getUuid(), (int) $shareLink->getId()));
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * A content type without URLs of its own has no page to share. Snippets get share links of their own (#260)
     *
     * @test
     */
    public function it_shows_neither_share_draft_nor_a_share_links_section_for_an_entry_without_a_url_of_its_own(): void
    {
        $client = self::signedInClient();
        /** @var FactoryInterface<Announcement> $factory */
        $factory = self::getContainer()->get('app.factory.announcement');
        $announcement = $factory->createNew();
        $announcement->setCurrentLocale('en_US');
        $announcement->setFallbackLocale('en_US');
        $announcement->setTitle('Free shipping');
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.announcement');
        $manager->persist($announcement);
        $manager->flush();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $announcement->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-overview-header] button[data-cms-dialog-open]'));
        self::assertCount(0, $crawler->filter('[data-section="share_links"]'));
    }

    /**
     * @test
     */
    public function it_deletes_an_entrys_share_links_with_the_entry(): void
    {
        self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::createShareLink($page);

        /** @var EntryDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EntryDeleterInterface::class);
        $deleter->delete($page);

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__share_link'));
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * What Share draft's dialog offers: target value => label
     *
     * @return array<string, string>
     */
    private static function targets(Crawler $crawler): array
    {
        $targets = [];
        foreach ($crawler->filter('#cms-share-draft select[name="target"] option') as $option) {
            self::assertInstanceOf(\DOMElement::class, $option);
            $targets[$option->getAttribute('value')] = trim($option->textContent);
        }

        return $targets;
    }

    /**
     * The Share links section's rows: channel, language, Shown to, Date, made by and the link
     *
     * @return list<list<string>>
     */
    private static function rows(Crawler $crawler): array
    {
        $rows = [];
        foreach ($crawler->filter('[data-section="share_links"] [data-share-link]') as $row) {
            $row = new Crawler($row);
            $rows[] = [
                trim($row->filter('[data-channel]')->text()),
                trim($row->filter('[data-locale]')->text()),
                trim($row->filter('[data-shown-to]')->text()),
                trim($row->filter('[data-date]')->text()),
                trim($row->filter('[data-created-by]')->text()),
                (string) $row->filter('input[data-url]')->attr('value'),
            ];
        }

        return $rows;
    }

    private static function addDanish(PageInterface $page): void
    {
        self::addTranslation($page, 'da_DK', 'Priser', 'priser');
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
