<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

final class PreviewTokenTest extends BuilderApiTestCase
{
    /**
     * @test
     */
    public function it_answers_with_the_url_of_the_preview_on_the_admins_own_host(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsString($response['url'] ?? null);
        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/about-us\?_cms_preview=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $response['url']);
    }

    /**
     * @test
     */
    public function its_url_shows_the_working_copy_of_a_never_published_page_in_the_channel_whatever_the_host(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        /** @var array{url: string} $response */
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);
        $client->request('GET', $response['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        self::assertSelectorTextSame('title', 'Setono CMS Test Store', 'The channel is WEB, though its hostname is localhost');
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US']);

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

        self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US'], 'not-the-token');

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

        self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);

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

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', $body + ['entry' => $page->getUuid()], $csrfToken);

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
    }

    /**
     * @test
     */
    public function it_refuses_an_entry_without_a_url_in_the_locale(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', '');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', ['entry' => $page->getUuid(), 'channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The entry has no URL in en_US', $response['error'] ?? null);
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
