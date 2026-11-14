<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

final class EntryTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const NEW_KEY = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /**
     * @test
     */
    public function showing_an_entry_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        $client->request('GET', '/admin/cms/api/entries/' . $page->getUuid());

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_shows_the_working_copy_in_the_entry_format_with_its_status(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/admin/cms/api/entries/' . $page->getUuid());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(
            sprintf(
                '{"entry":{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{},"translations":{"en_US":{"shown":true,"fields":{"slug":"about-us","title":"About us"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}},"status":"never_published"}',
                $page->getUuid(),
                $page->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                self::KEY,
            ),
            $client->getResponse()->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_answers_404_for_an_entry_it_does_not_have(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/api/entries/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function saving_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['changes' => []]);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function saving_needs_a_valid_csrf_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        self::openBuilder($client, $page);

        $body = ['changes' => [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::NEW_KEY, 'type' => 'heading', 'version' => 1]]]];

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), $body);
        self::assertResponseStatusCodeSame(403);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), $body, 'not-the-token');
        self::assertResponseStatusCodeSame(403);

        self::assertSame([], self::reloadPage($page)->getBlockTree()->blocks);
    }

    /**
     * @test
     */
    public function it_saves_a_change_set_straight_to_the_rows(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => ['key' => self::NEW_KEY, 'type' => 'heading', 'version' => 1, 'style' => null, 'fields' => [], 'translated' => [], 'conditions' => [], 'children' => []]],
            ['op' => 'set_value', 'key' => self::NEW_KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'World'],
            ['op' => 'set_value', 'key' => self::NEW_KEY, 'field' => 'level', 'locale' => null, 'value' => 'h3'],
            ['op' => 'remove_block', 'key' => self::KEY],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'never_published'], $response);

        $page = self::reloadPage($page);
        self::assertSame(
            sprintf('{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"level":"h3","text":{"en_US":"World"}},"translated":{},"conditions":[],"children":[]}]}', self::NEW_KEY),
            $page->getBlockTreeJson(),
        );
        self::assertFalse($page->isPublished());
    }

    /**
     * @test
     */
    public function it_refuses_an_invalid_change_set_and_saves_nothing(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Changed'],
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'subtitle', 'locale' => 'en_US', 'value' => 'Nope'],
        ]], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertIsString($response['error'] ?? null);
        self::assertStringContainsString('subtitle', $response['error']);
        self::assertSame(['en_US' => 'Hello'], self::reloadPage($page)->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_refuses_a_body_that_is_not_json(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('PATCH', '/admin/cms/api/entries/' . $page->getUuid(), [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken], '{');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @test
     */
    public function publishing_needs_a_valid_csrf_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        self::openBuilder($client, $page);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, 'not-the-token');

        self::assertResponseStatusCodeSame(403);
        self::assertFalse(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function publishing_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()));

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_publishes_an_entry_so_it_shows_at_its_url(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(404);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'published'], $response);
        self::assertTrue(self::reloadPage($page)->isPublished());

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }
}
