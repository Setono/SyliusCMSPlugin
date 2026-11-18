<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The block picker's Presets tab: the presets, with whether each is offered at a spot and why not, and a preset's
 * blocks made ready to insert into an entry. The test application registers hero, column_pair and product_spotlight.
 * See "Presets" in docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
 */
final class PresetsTest extends BuilderApiTestCase
{
    private const COLUMNS = '0193a5be-0000-7000-8000-000000000001';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @test
     */
    public function it_lists_the_code_presets_with_their_metadata_and_whether_each_is_offered_at_the_top_level_of_a_page_and_why_not(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()));

        self::assertSame(['column_pair', 'hero', 'product_spotlight'], array_keys($presets));
        $hero = $presets['hero'];
        self::assertSame(['id' => 'hero', 'fromCode' => true, 'name' => 'Hero', 'category' => 'Banners', 'description' => 'A heading and an introduction', 'thumbnail' => null, 'fits' => true, 'reasons' => [], 'issues' => []], array_diff_key($hero, ['blocks' => true]));
        self::assertSame(['Column isn\'t allowed here'], $presets['column_pair']['reasons']);
        self::assertFalse($presets['column_pair']['fits']);
        self::assertSame(['Needs: Product page'], $presets['product_spotlight']['reasons']);
        self::assertSame(['Layout', null], [$presets['column_pair']['category'], $presets['column_pair']['description']]);

        // Its blocks are ready to render in the live preview: the entry's locales, the translation keys resolved
        self::assertSame(['heading', 'rich_text'], array_column($hero['blocks'], 'type'));
        self::assertSame(['level' => 'h2', 'text' => ['en_US' => 'Winter sale']], ($hero['blocks'][0] ?? [])['fields'] ?? null);
    }

    /**
     * @test
     */
    public function it_offers_a_preset_inside_a_block_with_room_for_it_and_not_where_it_would_go_over_the_max(): void
    {
        $client = self::signedIn();
        $roomy = self::createPageWithBlocks('Roomy', 'roomy', [self::columns(2)]);
        $full = self::createPageWithBlocks('Full', 'full', [self::columns(3)]);

        $inRoomy = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s&parent=%s', $roomy->getUuid(), self::COLUMNS));
        $inFull = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s&parent=%s', $full->getUuid(), self::COLUMNS));

        self::assertTrue($inRoomy['column_pair']['fits']);
        self::assertSame(['Heading isn\'t allowed here', 'Rich text isn\'t allowed here'], $inRoomy['hero']['reasons']);
        self::assertSame(['At most 4 Column blocks fit here'], $inFull['column_pair']['reasons']);
    }

    /**
     * @test
     */
    public function it_offers_a_preset_whose_blocks_need_a_product_in_a_snippet_that_declares_it(): void
    {
        $client = self::signedIn();
        $snippet = self::createSnippet('Product notes', context: ['product']);

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $snippet->getUuid()));

        self::assertTrue($presets['product_spotlight']['fits']);
        self::assertSame([], $presets['product_spotlight']['reasons']);
    }

    /**
     * @test
     */
    public function it_answers_bad_request_without_an_entry_and_not_found_for_an_unknown_one(): void
    {
        $client = self::signedIn();

        self::json($client, 'GET', '/admin/cms/api/presets');
        self::assertResponseStatusCodeSame(400);

        self::json($client, 'GET', '/admin/cms/api/presets?entry=0193a5be-0000-7000-8000-00000000ffff');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_answers_a_preset_s_blocks_ready_to_insert_with_new_keys_and_the_translation_keys_resolved_in_each_of_the_entry_s_locales(): void
    {
        $client = self::signedIn();
        self::createLocale('da_DK');
        self::createLocale('de_DE');
        $page = self::createPage('About us', 'about-us');
        self::addTranslation($page, 'da_DK', 'Om os', 'om-os');
        self::addTranslation($page, 'de_DE', 'Über uns', 'ueber-uns');

        $response = self::json($client, 'POST', '/admin/cms/api/presets/hero/insert', ['entry' => $page->getUuid()]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame([], $response['notices']);
        self::assertIsArray($response['blocks']);
        [$heading, $richText] = $response['blocks'];
        self::assertIsArray($heading);
        self::assertIsArray($richText);
        self::assertIsString($heading['key'] ?? null);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $heading['key']);
        self::assertNotSame('0193a5be-0000-7000-8000-00000000a001', $heading['key']);
        // German has no translation of the key, so it stays empty and counts as untranslated
        self::assertSame(['level' => 'h2', 'text' => ['da_DK' => 'Vinterudsalg', 'en_US' => 'Winter sale']], $heading['fields']);
        self::assertSame(['content' => ['en_US' => ['content' => [['content' => [
            ['text' => 'Up to ', 'type' => 'text'],
            ['marks' => [['type' => 'bold']], 'text' => '50%', 'type' => 'text'],
            ['text' => ' off.', 'type' => 'text'],
        ], 'type' => 'paragraph']], 'type' => 'doc']]], $richText['fields']);
    }

    /**
     * @test
     */
    public function it_drops_the_values_of_locales_the_entry_does_not_have(): void
    {
        $client = self::signedIn();
        $snippet = self::createSnippet('Product notes', context: ['product']);

        $response = self::json($client, 'POST', '/admin/cms/api/presets/product_spotlight/insert', ['entry' => $snippet->getUuid()]);

        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        $note = $response['blocks'][0] ?? null;
        self::assertIsArray($note);
        self::assertSame(['text' => ['en_US' => 'Made to last']], $note['fields'] ?? null);
    }

    /**
     * @test
     */
    public function it_answers_not_found_for_a_preset_it_does_not_have_and_bad_request_without_an_entry(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');

        self::json($client, 'POST', '/admin/cms/api/presets/banner/insert', ['entry' => $page->getUuid()]);
        self::assertResponseStatusCodeSame(404);

        self::json($client, 'POST', '/admin/cms/api/presets/hero/insert', []);
        self::assertResponseStatusCodeSame(400);
    }

    private static function columns(int $columns): StoredBlock
    {
        return new StoredBlock(self::COLUMNS, 'columns', 1, children: array_map(static fn (int $column): StoredBlock => new StoredBlock(sprintf('0193a5be-0000-7000-8000-0000000001%02d', $column), 'column', 1), range(1, $columns)));
    }
}
