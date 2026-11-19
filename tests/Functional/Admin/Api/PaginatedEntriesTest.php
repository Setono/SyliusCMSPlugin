<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Only an entry with its own URL paginates, through one Entries block in its own tree, filled by a source: publish
 * refuses a second paginated Entries block, and one in a snippet, and the builder's validation flags them at their
 * Split into pages field. See "Pagination" in docs/spec/collections.md
 */
final class PaginatedEntriesTest extends BuilderApiTestCase
{
    private const FIRST = '0193a5be-0000-7000-8000-0000000000e1';

    private const SECOND = '0193a5be-0000-7000-8000-0000000000e2';

    /**
     * @test
     */
    public function it_validates_a_page_with_one_paginated_entries_block(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('Blog', 'blog', [self::entries(self::FIRST), self::entries(self::SECOND, paginate: false)]);

        self::assertSame(['errors' => []], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_a_second_paginated_entries_block(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('Blog', 'blog', [self::entries(self::FIRST), self::entries(self::SECOND)]);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([
            ['block' => self::SECOND, 'field' => 'paginate', 'locale' => null, 'message' => 'Only one list on a page can be split into pages. Turn off "Split into pages" here, or on the other list.'],
        ], $response['errors'] ?? null);
    }

    /**
     * A snippet in a slot on a taxon page would clash with Sylius's own ?page=N
     *
     * @test
     */
    public function it_refuses_to_publish_a_paginated_entries_block_in_a_snippet(): void
    {
        $client = self::signedIn();
        $snippet = self::createSnippet('Latest posts', [self::entries(self::FIRST)]);
        $csrfToken = self::openBuilder($client, $snippet);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $snippet->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([
            ['block' => self::FIRST, 'field' => 'paginate', 'locale' => null, 'message' => 'Only an entry with its own URL can split a list into pages. Turn off "Split into pages".'],
        ], $response['errors'] ?? null);
    }

    /**
     * @test
     */
    public function it_flags_a_paginated_entries_block_with_hand_picked_entries_in_the_builder_s_validation(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('Picks', 'picks', [self::entries(self::FIRST, [])]);

        self::assertSame(['errors' => [
            ['block' => self::FIRST, 'field' => 'paginate', 'locale' => null, 'message' => 'A list can only be split into pages when a source fills it. Pick a source under "Filled by", or turn off "Split into pages".'],
        ]], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));
    }

    /**
     * @param array<array-key, mixed>|null $entries the Entries field's stored value, the blog's posts unless given
     */
    private static function entries(string $key, ?array $entries = null, bool $paginate = true): StoredBlock
    {
        return new StoredBlock($key, 'entries', 1, fields: [
            'entries' => $entries ?? ['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => 9],
            'display' => 'grid',
            'paginate' => $paginate,
        ]);
    }
}
