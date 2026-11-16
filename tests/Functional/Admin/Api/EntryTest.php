<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

final class EntryTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const NEW_KEY = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const GROUP_KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40';

    private const COPY_KEY = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f61';

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
    public function it_shows_the_working_copy_in_the_entry_format_with_its_settings_and_status(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/admin/cms/api/entries/' . $page->getUuid());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(
            sprintf(
                '{"entry":{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{"noindex":false},"translations":{"en_US":{"shown":true,"fields":{"metaDescription":null,"metaTitle":null,"slug":"about-us","title":"About us"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}},"lockToken":0,"settings":{"enabled":true,"showFrom":null,"showUntil":null,"channels":["WEB"],"audience":{"mode":"everyone","groups":[]}},"status":"never_published","schedule":null,"editors":[],"completeness":{"en_US":{"untranslated":0,"outdated":0}}}',
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

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => []]);

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

        $body = ['lockToken' => 0, 'changes' => [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::NEW_KEY, 'type' => 'heading', 'version' => 1]]]];

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

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => ['key' => self::NEW_KEY, 'type' => 'heading', 'version' => 1, 'style' => null, 'fields' => [], 'translated' => [], 'conditions' => [], 'children' => []]],
            ['op' => 'set_value', 'key' => self::NEW_KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'World'],
            ['op' => 'set_value', 'key' => self::NEW_KEY, 'field' => 'level', 'locale' => null, 'value' => 'h3'],
            ['op' => 'remove_block', 'key' => self::KEY],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'never_published', 'lockToken' => 1], $response);

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

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
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
    public function it_saves_duplicated_blocks_under_their_new_keys(): void
    {
        $client = self::createClient();
        $group = new StoredBlock(self::GROUP_KEY, 'group', 1, children: [self::heading(self::KEY, 'Hello')]);
        $page = self::createPageWithBlocks('About us', 'about-us', [$group]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => self::group(self::COPY_KEY, self::NEW_KEY)],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        $tree = self::reloadPage($page)->getBlockTree();
        self::assertSame([self::GROUP_KEY, self::KEY, self::COPY_KEY, self::NEW_KEY], $tree->keys());
        self::assertSame(['en_US' => 'Hello'], $tree->blocks[1]->children[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_refuses_a_change_set_that_reuses_a_key_and_saves_nothing(): void
    {
        $client = self::createClient();
        $group = new StoredBlock(self::GROUP_KEY, 'group', 1, children: [self::heading(self::KEY, 'Hello')]);
        $page = self::createPageWithBlocks('About us', 'about-us', [$group]);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Changed'],
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => self::group(self::COPY_KEY, self::KEY)],
        ]], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertIsString($response['error'] ?? null);
        self::assertStringContainsString(self::KEY, $response['error']);
        self::assertSame([self::GROUP_KEY, self::KEY], self::reloadPage($page)->getBlockTree()->keys());
        self::assertSame(['en_US' => 'Hello'], self::reloadPage($page)->getBlockTree()->blocks[0]->children[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_saves_the_block_style_picked_for_a_block_and_taking_it_away(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::GROUP_KEY, 'group', 1),
            new StoredBlock(self::KEY, 'group', 1, style: 'muted'),
        ]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_style', 'key' => self::GROUP_KEY, 'style' => 'dark'],
            ['op' => 'set_style', 'key' => self::KEY, 'style' => null],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        $blocks = self::reloadPage($page)->getBlockTree()->blocks;
        self::assertSame('dark', $blocks[0]->style);
        self::assertNull($blocks[1]->style);
    }

    /**
     * @test
     */
    public function it_refuses_a_block_style_the_block_type_does_not_offer(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_style', 'key' => self::KEY, 'style' => 'dark'],
        ]], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The block type "heading" has no block style "dark"', $response['error'] ?? null);
        self::assertNull(self::reloadPage($page)->getBlockTree()->blocks[0]->style);
    }

    /**
     * A group with a heading inside, as the builder sends a copy of one
     *
     * @return array<string, mixed>
     */
    private static function group(string $key, string $headingKey): array
    {
        $block = static fn (string $key, string $type, array $fields = [], array $children = []): array => [
            'key' => $key, 'type' => $type, 'version' => 1, 'style' => null, 'fields' => $fields, 'translated' => [], 'conditions' => [], 'children' => $children,
        ];

        return $block($key, 'group', [], [$block($headingKey, 'heading', ['text' => ['en_US' => 'Hello']])]);
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
