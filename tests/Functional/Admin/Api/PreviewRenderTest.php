<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Symfony\Component\Clock\MockClock;

final class PreviewRenderTest extends BuilderApiTestCase
{
    use Customers;

    private const FIRST = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const SECOND = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_renders_the_given_blocks_of_the_working_copy_and_answers_with_their_html_with_markers_by_key(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', self::uri($page), ['blocks' => [
            self::storedHeading(self::FIRST, 'Changed in the builder'),
            self::storedHeading(self::SECOND, 'Not saved yet', 'h3'),
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertSame([self::FIRST, self::SECOND], array_keys($response['blocks']));
        [$first, $second] = [$response['blocks'][self::FIRST], $response['blocks'][self::SECOND]];
        self::assertIsString($first);
        self::assertIsString($second);
        self::assertMatchesRegularExpression('#^<!--cms:block ' . self::FIRST . ' heading--><h2 class="cms-heading">.*Changed in the builder.*</h2>\s*<!--/cms:block ' . self::FIRST . '-->$#', $first);
        self::assertMatchesRegularExpression('#^<!--cms:block ' . self::SECOND . ' heading--><h3 class="cms-heading">.*Not saved yet.*</h3>\s*<!--/cms:block ' . self::SECOND . '-->$#', $second);
    }

    /**
     * @test
     */
    public function it_marks_the_fields_editors_can_type_in_place(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', self::uri($page), ['blocks' => [
            self::storedHeading(self::FIRST, 'Typed in place'),
            self::storedRichText(self::SECOND, ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'A '],
                ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => ' word'],
            ]]]]),
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertSame(
            '<h2 class="cms-heading"><!--cms:field ' . self::FIRST . ' text text-->Typed in place<!--/cms:field ' . self::FIRST . ' text--></h2>',
            self::between($response['blocks'][self::FIRST] ?? null, self::FIRST),
            'The heading level is a choice, which is not typed in place, so only the text is marked',
        );
        self::assertSame(
            '<div class="cms-rich-text"><!--cms:field ' . self::SECOND . ' content rich_text--><p>A <strong>bold</strong> word</p><!--/cms:field ' . self::SECOND . ' content--></div>',
            self::between($response['blocks'][self::SECOND] ?? null, self::SECOND),
        );
    }

    /**
     * What a block's HTML holds between its block markers, trimmed
     */
    private static function between(mixed $html, string $key): string
    {
        self::assertIsString($html);
        self::assertStringStartsWith('<!--cms:block ' . $key . ' ', $html);
        self::assertStringEndsWith('<!--/cms:block ' . $key . '-->', $html);

        return trim(substr($html, (int) strpos($html, '-->') + 3, -strlen('<!--/cms:block ' . $key . '-->')));
    }

    /**
     * @test
     */
    public function it_keeps_its_answer_out_of_caches_and_search_engines(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', self::uri($page), ['blocks' => [self::storedHeading(self::FIRST, 'Hello')]], $csrfToken);

        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * The token's Shown to and Date decide the blocks' visibility conditions here as on the page.
     * See "Shown to and Date" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_renders_the_blocks_the_tokens_shown_to_and_date_see(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        $nextWeek = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+7 days');
        $blocks = ['blocks' => [
            ['conditions' => [['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['wholesale']]]]] + self::storedHeading(self::FIRST, 'Wholesale prices'),
            ['conditions' => [['kind' => 'date_window', 'negate' => false, 'params' => ['from' => $nextWeek->format('Y-m-d\TH:i:s\Z')]]]] + self::storedHeading(self::SECOND, 'Campaign'),
        ]];

        /** @var array{blocks: array<string, string>} $shown */
        $shown = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page, shownTo: 'wholesale', date: $nextWeek), $blocks, $csrfToken);
        /** @var array{blocks: array<string, string>} $hidden */
        $hidden = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page, shownTo: '@guest'), $blocks, $csrfToken);

        self::assertStringContainsString('Wholesale prices', $shown['blocks'][self::FIRST]);
        self::assertStringContainsString('Campaign', $shown['blocks'][self::SECOND]);
        self::assertSame(['blocks' => [self::FIRST => '', self::SECOND => '']], $hidden);
    }

    /**
     * @test
     */
    public function it_renders_nothing_for_a_block_of_a_type_the_code_no_longer_has(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', self::uri($page), ['blocks' => [['type' => 'removed_from_code'] + self::storedHeading(self::FIRST, 'Hello')]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['blocks' => [self::FIRST => '']], $response);
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        self::json($client, 'POST', self::uri($page), ['blocks' => []]);

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

        self::json($client, 'POST', self::uri($page), ['blocks' => []], 'not-the-token');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @test
     */
    public function it_needs_a_valid_preview_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);
        $signedWithAnotherSecret = new PreviewTokenSigner('not the secret', new MockClock());

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . $signedWithAnotherSecret->sign(new Preview($page->getUuid(), 'WEB', 'en_US')), ['blocks' => []], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The request has no valid preview token', $response['error'] ?? null);
    }

    /**
     * @test
     */
    public function it_answers_404_when_the_previewed_entry_is_gone(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e'), ['blocks' => []], $csrfToken);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     *
     * @dataProvider invalidBodies
     */
    public function it_refuses_blocks_it_cannot_read(string $body, string $error): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('POST', self::uri($page), [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken], $body);

        self::assertResponseStatusCodeSame(400);
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertIsString($response['error'] ?? null);
        self::assertStringContainsString($error, $response['error']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'no blocks' => ['{}', 'blocks'];
        yield 'blocks that are not a list' => ['{"blocks": {"a": 1}}', 'blocks'];
        yield 'a block without a key' => ['{"blocks": [{"type": "heading", "version": 1}]}', 'key'];
    }

    /**
     * @test
     */
    public function it_refuses_a_body_that_is_not_json(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page);

        $client->request('POST', self::uri($page), [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken], '{');

        self::assertResponseStatusCodeSame(400);
    }

    private static function uri(PageInterface $page): string
    {
        return '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page->getUuid());
    }

    /**
     * A heading block in the stored block tree format, as the builder holds it
     *
     * @return array<string, mixed>
     */
    private static function storedHeading(string $key, string $text, string $level = 'h2'): array
    {
        return [
            'key' => $key,
            'type' => 'heading',
            'version' => 1,
            'style' => null,
            'fields' => ['text' => ['en_US' => $text], 'level' => $level],
            'translated' => (object) [],
            'conditions' => [],
            'children' => [],
        ];
    }

    /**
     * A rich text block in the stored block tree format, as the builder holds it
     *
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private static function storedRichText(string $key, array $document): array
    {
        return [
            'key' => $key,
            'type' => 'rich_text',
            'version' => 1,
            'style' => null,
            'fields' => ['content' => ['en_US' => $document]],
            'translated' => (object) [],
            'conditions' => [],
            'children' => [],
        ];
    }
}
