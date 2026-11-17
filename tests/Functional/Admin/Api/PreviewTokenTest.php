<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\DomCrawler\Crawler;

final class PreviewTokenTest extends BuilderApiTestCase
{
    use Customers;

    private const KEY = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_answers_with_the_url_of_the_preview_on_the_admins_own_host(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsString($response['url'] ?? null);
        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/about-us\?_cms_preview=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $response['url']);
    }

    /**
     * @test
     */
    public function it_answers_a_url_that_shows_the_working_copy_of_a_never_published_page_in_the_channel_whatever_the_host(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        /** @var array{url: string} $response */
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);
        $client->request('GET', $response['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        self::assertSelectorExists('link[rel="alternate"][hreflang="x-default"][href$="/en_US/about-us"]', 'The channel is WEB, whose default locale is en_US, though its hostname is localhost');
    }

    /**
     * A snippet with no placement previews on a plain shop page. See "Snippet preview" in docs/spec/builder.md
     *
     * @test
     */
    public function it_answers_a_plain_shop_page_that_shows_the_working_copy_of_a_snippet_marked_for_the_builder(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $snippet = self::createSnippet('Free shipping', [self::heading(self::KEY, 'Free shipping over $50')]);
        $csrfToken = self::openBuilder($client, $snippet);

        /** @var array{url: string} $response */
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $snippet->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/_cms/snippet-preview\?_cms_preview=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $response['url']);

        $crawler = $client->request('GET', $response['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            sprintf('<!--cms:block %s heading--><h2 class="cms-heading"><!--cms:field %1$s text text-->Free shipping over $50<!--/cms:field %1$s text--></h2><!--/cms:block %1$s-->', self::KEY),
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-slot')->html()),
        );
        self::assertSelectorExists('#footer', 'It shows in the shop\'s own layout');
    }

    /**
     * @test
     */
    public function it_shows_the_plain_snippet_page_only_to_the_builder_s_preview_of_a_snippet(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/_cms/snippet-preview');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/_cms/snippet-preview?_cms_preview=' . self::previewToken($page));
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The builder previews a channel's homepage at the channel's root, /{locale}/, where shoppers see it.
     * See "Rendering the homepage" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_answers_the_channels_root_for_the_channels_homepage(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $page = self::createPage('Welcome', 'welcome', 'Hello');
        self::setHomepageOf($page, 'WEB');
        $csrfToken = self::openBuilder($client, $page);

        /** @var array{url: string} $response */
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/\?_cms_preview=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $response['url']);

        $client->request('GET', $response['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
    }

    /**
     * The token carries Shown to and Date, which the page follows. See "Shown to and Date" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_answers_a_url_that_shows_the_page_to_shown_to_at_the_date(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $nextWeek = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+7 days')->setTime(12, 0);
        $wholesale = new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'heading', 1, conditions: [['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['wholesale']]]]);
        $wholesale->setValue('text', 'en_US', 'Wholesale prices');
        $campaign = new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'heading', 1, conditions: [['kind' => 'date_window', 'negate' => false, 'params' => ['from' => $nextWeek->format('Y-m-d\TH:i:s\Z')]]]);
        $campaign->setValue('text', 'en_US', 'Campaign');
        $page = self::createPageWithBlocks('About us', 'about-us', [$wholesale, $campaign]);
        $csrfToken = self::openBuilder($client, $page);

        /** @var array{url: string} $response */
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => 'wholesale', 'date' => $nextWeek->format('Y-m-d\TH:i:s\Z')], $csrfToken);
        $crawler = $client->request('GET', $response['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Wholesale prices', 'Campaign'], $crawler->filter('.cms-tree .cms-heading')->each(static fn (Crawler $heading): string => $heading->text()));

        /** @var array{url: string} $response */
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@no_group', 'date' => null], $csrfToken);
        $client->request('GET', $response['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorNotExists('.cms-tree .cms-heading', 'Signed in without a group, now, neither block shows');
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest']);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_needs_a_valid_csrf_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        self::openBuilder($client, $page);

        self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], 'not-the-token');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @test
     */
    public function it_answers_404_for_an_entry_it_does_not_have(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     *
     * @dataProvider invalidRequests
     *
     * @param array<string, mixed> $body
     */
    public function it_refuses_what_it_cannot_preview(array $body, string $error): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', $body + ['entry' => $page->getUuid(), 'shownTo' => '@guest'], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertIsString($response['error'] ?? null);
        self::assertStringContainsString($error, $response['error']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'no channel' => [['locale' => 'en_US'], 'channel'];
        yield 'an unknown channel' => [['channel' => 'NOPE', 'locale' => 'en_US'], 'NOPE'];
        yield 'no locale' => [['channel' => 'WEB'], 'locale'];
        yield 'a locale the channel does not have' => [['channel' => 'WEB', 'locale' => 'da_DK'], 'da_DK'];
        yield 'an entry that is not a string' => [['entry' => 1, 'channel' => 'WEB', 'locale' => 'en_US'], 'entry'];
        yield 'no Shown to' => [['channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => null], 'shownTo'];
        yield 'a Shown to that is not a string' => [['channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => 1], 'shownTo'];
        yield 'a Shown to that is no customer group value' => [['channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@everyone'], '@everyone'];
        yield 'an empty Shown to' => [['channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => ''], 'shownTo'];
        yield 'a date that is not a string' => [['channel' => 'WEB', 'locale' => 'en_US', 'date' => 1_795_000_000], 'date'];
        yield 'a date that is not a UTC date and time' => [['channel' => 'WEB', 'locale' => 'en_US', 'date' => '2026-12-01T10:00:00+01:00'], '2026-12-01T10:00:00+01:00'];
        yield 'a date that does not exist' => [['channel' => 'WEB', 'locale' => 'en_US', 'date' => '2026-02-30T10:00:00Z'], '2026-02-30T10:00:00Z'];
    }

    /**
     * @test
     */
    public function it_refuses_an_entry_without_a_url_in_the_locale(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', '');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The entry has no URL in en_US', $response['error'] ?? null);
    }

    /**
     * The preview shows the entry in one of its channels
     *
     * @test
     */
    public function it_refuses_a_channel_the_entry_is_not_in(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', channels: ['OTHER']);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame("The entry isn't in the channel WEB", $response['error'] ?? null);
    }

    /**
     * @test
     */
    public function it_refuses_a_body_that_is_not_json(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('POST', '/admin/cms/api/preview/token', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken], '{');

        self::assertResponseStatusCodeSame(400);
    }
}
