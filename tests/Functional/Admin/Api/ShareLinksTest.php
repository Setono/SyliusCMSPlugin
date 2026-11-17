<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;
use Setono\SyliusCMSPlugin\Tests\Support\ShareLinks;

/**
 * The builder makes, lists and revokes an entry's share links. See "Share links" in docs/spec/publishing.md and the
 * share_link_* endpoints in docs/spec/builder.md
 */
final class ShareLinksTest extends BuilderApiTestCase
{
    use Admins;
    use ShareLinks;

    /**
     * @test
     */
    public function it_makes_a_share_link_for_a_channel_and_locale_with_shown_to_and_date_fixed_and_who_made_it(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $shareLink = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()), ['channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);

        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($shareLink);
        self::assertSame(['id', 'url', 'channel', 'locale', 'shownTo', 'date', 'createdAt', 'createdBy', 'placement', 'sample'], array_keys($shareLink));
        self::assertIsInt($shareLink['id']);
        self::assertIsString($shareLink['url']);
        self::assertMatchesRegularExpression('#^http://localhost/en_US/about-us\?_cms_share=[A-Za-z0-9_-]{43}$#', $shareLink['url']);
        self::assertSame('WEB', $shareLink['channel']);
        self::assertSame('en_US', $shareLink['locale']);
        self::assertSame('@guest', $shareLink['shownTo']);
        self::assertNull($shareLink['date']);
        self::assertIsString($shareLink['createdAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $shareLink['createdAt']);
        self::assertSame('John Doe', $shareLink['createdBy']);
        self::assertNull($shareLink['placement'], 'A page shows at its own URL');
        self::assertNull($shareLink['sample']);

        // Anyone with the link sees the draft, without an admin account
        $client->restart();
        $client->request('GET', $shareLink['url']);
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
    }

    /**
     * Date starts at the start of the entry's date window when that's in the future
     *
     * @test
     */
    public function it_fixes_the_date_at_the_start_of_a_date_window_in_the_future(): void
    {
        $client = self::createClient();
        $page = self::createPage('Christmas', 'christmas', 'Ho ho ho');
        $page->setDateWindow(new DateWindow(new \DateTimeImmutable('2099-12-01T00:00:00Z'), null));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
        $csrfToken = self::openBuilder($client, $page);

        $shareLink = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()), ['channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);

        self::assertIsArray($shareLink);
        self::assertSame('2099-12-01T00:00:00Z', $shareLink['date'] ?? null);
    }

    /**
     * @test
     */
    public function it_puts_a_share_links_url_on_the_channels_hostname(): void
    {
        $client = self::createClient();
        self::createChannel('B2B', hostname: 'b2b.localhost');
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB', 'B2B']);
        $csrfToken = self::openBuilder($client, $page);

        $shareLink = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()), ['channel' => 'B2B', 'locale' => 'en_US'], $csrfToken);

        self::assertIsArray($shareLink);
        self::assertIsString($shareLink['url'] ?? null);
        self::assertStringStartsWith('http://b2b.localhost/en_US/about-us?_cms_share=', $shareLink['url']);

        $client->restart();
        $client->request('GET', $shareLink['url']);
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
    }

    /**
     * @test
     *
     * @dataProvider provideUnavailableShareLinks
     *
     * @param array<string, mixed> $body
     */
    public function it_refuses_a_share_link_the_entry_has_no_page_for(array $body, string $error): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        self::createChannel('B2B');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()), $body, $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => $error], $response);
        self::assertSame([], self::shareLinksOf($page));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideUnavailableShareLinks(): iterable
    {
        yield 'no channel' => [['locale' => 'en_US'], 'The body needs the channel and the locale, each as a string'];
        yield 'a locale that is not a string' => [['channel' => 'WEB', 'locale' => ['en_US']], 'The body needs the channel and the locale, each as a string'];
        yield 'a channel that does not exist' => [['channel' => 'GONE', 'locale' => 'en_US'], 'There is no channel GONE'];
        yield 'a channel the entry is not in' => [['channel' => 'B2B', 'locale' => 'en_US'], 'The entry isn\'t in the channel B2B'];
        yield 'a locale the channel does not have' => [['channel' => 'WEB', 'locale' => 'da_DK'], 'The channel WEB has no locale da_DK'];
    }

    /**
     * @test
     */
    public function it_refuses_a_body_that_is_not_json(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()), [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken], '{');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @test
     */
    public function it_lists_the_entrys_share_links_newest_first(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::addDanish($page);
        $english = self::createShareLink($page);
        $danish = self::createShareLink($page, locale: 'da_DK');

        $client->loginUser(self::admin(), 'admin');
        $response = self::json($client, 'GET', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['shareLinks'] ?? null);
        self::assertSame([$danish->getId(), $english->getId()], array_column($response['shareLinks'], 'id'));
        self::assertSame(['da_DK', 'en_US'], array_column($response['shareLinks'], 'locale'));
        $first = $response['shareLinks'][0] ?? null;
        self::assertIsArray($first);
        self::assertSame('http://localhost/da_DK/om-os?_cms_share=' . $danish->getToken(), $first['url'] ?? null);
    }

    /**
     * @test
     */
    public function it_deletes_a_revoked_share_link_so_its_token_answers_as_for_a_shopper(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);
        $token = $shareLink->getToken();
        $csrfToken = self::openBuilder($client, $page);

        $client->request('DELETE', sprintf('/admin/cms/api/share-links/%d', (int) $shareLink->getId()), [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::shareLinksOf($page));

        $client->restart();
        $client->request('GET', '/en_US/about-us?_cms_share=' . $token);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_share_link_that_is_not_there(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('DELETE', '/admin/cms/api/share-links/999999', [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_needs_the_builders_csrf_token_to_make_and_revoke_a_share_link(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);
        self::openBuilder($client, $page);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()), ['channel' => 'WEB', 'locale' => 'en_US'], 'not the token');
        self::assertResponseStatusCodeSame(403);

        $client->request('DELETE', sprintf('/admin/cms/api/share-links/%d', (int) $shareLink->getId()), [], [], ['HTTP_X_CSRF_TOKEN' => 'not the token']);
        self::assertResponseStatusCodeSame(403);

        self::assertCount(1, self::shareLinksOf($page));
    }

    /**
     * @test
     */
    public function it_makes_share_links_for_admin_users_only(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);

        $client->request('GET', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()));
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('POST', sprintf('/admin/cms/api/entries/%s/share-links', $page->getUuid()));
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('DELETE', sprintf('/admin/cms/api/share-links/%d', (int) $shareLink->getId()));
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->loginUser(self::admin(), 'admin');
        $client->request('GET', '/admin/cms/api/entries/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40/share-links');
        self::assertResponseStatusCodeSame(404);
    }

    private static function addDanish(PageInterface $page): void
    {
        self::addTranslation($page, 'da_DK', 'Om os', 'om-os');
    }
}
