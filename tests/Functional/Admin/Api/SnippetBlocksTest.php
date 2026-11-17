<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The blocks "Detach to edit here" copies in place of a Snippet block: those the snippet shows, from its published
 * content, upgraded. See "Snippet references" in docs/spec/builder.md
 */
final class SnippetBlocksTest extends BuilderApiTestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const PROMO = '0193a5be-0000-7000-8000-000000000002';

    private const GROUP = '0193a5be-0000-7000-8000-000000000003';

    private const NESTED = '0193a5be-0000-7000-8000-000000000004';

    private const NESTED_PROMO = '0193a5be-0000-7000-8000-000000000005';

    /**
     * @test
     */
    public function it_answers_the_blocks_of_a_snippet_s_published_content_leaving_out_those_of_types_the_code_no_longer_has(): void
    {
        $client = self::signedIn();
        $group = new StoredBlock(self::GROUP, 'group', 1, children: [self::heading(self::NESTED, 'Nested'), new StoredBlock(self::NESTED_PROMO, 'promo', 1)]);
        $footer = self::createSnippet('Footer', [self::heading(self::HEADING, 'Free shipping over $50'), new StoredBlock(self::PROMO, 'promo', 1), $group], published: true);
        self::saveChanges($footer, [['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Free shipping over $40']]);

        $response = self::json($client, 'GET', sprintf('/admin/cms/api/snippets/%s/blocks', $footer->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertSame([self::HEADING, self::GROUP], array_column($response['blocks'], 'key'));
        self::assertSame([
            'key' => self::HEADING,
            'type' => 'heading',
            'version' => 1,
            'style' => null,
            'fields' => ['text' => ['en_US' => 'Free shipping over $50']],
            'translated' => [],
            'conditions' => [],
            'children' => [],
        ], $response['blocks'][0]);
        self::assertIsArray($response['blocks'][1]['children'] ?? null);
        self::assertSame([self::NESTED], array_column($response['blocks'][1]['children'], 'key'));
    }

    /**
     * @test
     */
    public function it_answers_not_found_for_an_entry_that_is_not_a_snippet(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us', 'Hello');

        self::json($client, 'GET', sprintf('/admin/cms/api/snippets/%s/blocks', $page->getUuid()));

        self::assertResponseStatusCodeSame(404);
    }
}
