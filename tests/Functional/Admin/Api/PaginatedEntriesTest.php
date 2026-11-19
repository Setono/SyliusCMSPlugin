<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Only an entry with its own URL paginates, through one Entries block in its own tree, filled by a source: publish
 * refuses a second paginated Entries block, and one in a snippet, and the builder's validation flags them at their
 * Split into pages field. So the block picker doesn't offer a preset holding one where publish would refuse it. See
 * "Pagination" in docs/spec/collections.md and "Where a preset is offered" in docs/spec/layouts-and-presets.md
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
     * @test
     */
    public function the_block_picker_offers_a_preset_with_a_paginated_entries_block_only_where_it_may_paginate(): void
    {
        $client = self::signedIn();
        $preset = self::saveBlockPreset('Paged posts', self::entries(self::FIRST))->id();
        $handPicked = self::saveBlockPreset('Paged picks', self::entries(self::FIRST, []))->id();
        $page = self::createPage('Blog', 'blog');
        $paginating = self::createPageWithBlocks('News', 'news', [self::entries(self::SECOND)]);
        $snippet = self::createSnippet('Latest posts');

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()));
        self::assertTrue($presets[$preset]['fits']);
        self::assertSame(['Splits a list into pages that no source fills'], $presets[$handPicked]['reasons']);

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $paginating->getUuid()));
        self::assertFalse($presets[$preset]['fits']);
        self::assertSame(['Splits a list into pages, which another list here already does'], $presets[$preset]['reasons']);

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $snippet->getUuid()));
        self::assertFalse($presets[$preset]['fits']);
        self::assertSame(['Splits a list into pages, which only an entry with its own URL can'], $presets[$preset]['reasons']);
    }

    private static function saveBlockPreset(string $name, StoredBlock $block): Preset
    {
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);

        return $saver->save(new Preset($name, [$block]));
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
