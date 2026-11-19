<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Editors save blocks as presets of their own from the builder, replace their blocks, rename and recategorise them, and
 * delete them. Saving takes effect at once, and never changes the entries a preset was inserted into. See "Editor
 * presets" in docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
 */
final class EditorPresetsTest extends BuilderApiTestCase
{
    use ClockSensitiveTrait;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const COLUMNS = '0193a5be-0000-7000-8000-000000000002';

    private const COLUMN = '0193a5be-0000-7000-8000-000000000003';

    private const BUTTON = '0193a5be-0000-7000-8000-000000000004';

    private const RICH_TEXT = '0193a5be-0000-7000-8000-000000000005';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private const DATE_TIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    /**
     * @test
     */
    public function it_saves_the_selected_blocks_under_different_parents_as_an_editor_preset_in_tree_order(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/presets', [
            'entry' => $page->getUuid(),
            'blocks' => [self::RICH_TEXT, self::BUTTON, self::HEADING],
            'name' => ' Promo ',
            'category' => 'Campaigns',
            'description' => '',
        ], $csrfToken);

        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($response);
        $preset = $response['preset'] ?? null;
        self::assertIsArray($preset);
        self::assertIsString($preset['id'] ?? null);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $preset['id']);
        self::assertIsString($preset['changedAt'] ?? null);
        self::assertMatchesRegularExpression(self::DATE_TIME_PATTERN, $preset['changedAt']);
        self::assertSame(['name' => 'Promo', 'category' => 'Campaigns', 'description' => null], array_intersect_key($preset, ['name' => true, 'category' => true, 'description' => true]));

        // Offered in the Presets tab of another page, with its blocks, each with its children, in tree order
        $other = self::createPage('Contact', 'contact');
        $listed = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $other->getUuid()))[$preset['id']] ?? null;
        self::assertIsArray($listed);
        self::assertSame(['id' => $preset['id'], 'fromCode' => false, 'name' => 'Promo', 'category' => 'Campaigns', 'description' => null, 'thumbnail' => null], array_intersect_key($listed, array_flip(['id', 'fromCode', 'name', 'category', 'description', 'thumbnail'])));
        self::assertSame(['heading', 'button', 'rich_text'], array_column($listed['blocks'], 'type'));

        // Its category is suggested from now on, beside the code presets'
        $index = self::json($client, 'GET', sprintf('/admin/cms/api/presets?entry=%s', $other->getUuid()));
        self::assertIsArray($index);
        self::assertSame(['Banners', 'Blog', 'Campaigns', 'Content', 'Layout', 'Navigation', 'Pages', 'Products'], $index['categories'] ?? null);

        // The page it was saved from is left as it is
        self::assertSame([self::HEADING, self::COLUMNS, self::RICH_TEXT], array_map(static fn (StoredBlock $block): string => $block->key, self::reloadPage($page)->getBlockTree()->blocks));
    }

    /**
     * @test
     */
    public function it_inserts_an_editor_preset_by_its_uuid_with_new_keys(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $uuid = self::savePreset($client, self::openBuilder($client, $page), $page->getUuid(), [self::COLUMNS], 'Two columns');

        $other = self::createPage('Contact', 'contact');
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/presets/%s/insert', $uuid), ['entry' => $other->getUuid()]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertSame(['columns'], array_column($response['blocks'], 'type'));
        self::assertIsArray($response['blocks'][0] ?? null);
        self::assertNotSame(self::COLUMNS, $response['blocks'][0]['key'] ?? null);
    }

    /**
     * @test
     */
    public function it_refuses_a_preset_without_a_name_without_blocks_or_with_a_block_the_entry_does_not_have(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);

        // Each with the error in the admin user's language, which the builder shows
        foreach ([
            [['blocks' => [self::HEADING], 'name' => '  '], 'A preset needs a name.'],
            [['blocks' => [self::HEADING]], 'A preset needs a name.'],
            [['blocks' => [], 'name' => 'Promo'], 'Select one or more blocks to save as a preset.'],
            [['blocks' => ['0193a5be-0000-7000-8000-0000000000ff'], 'name' => 'Promo'], 'Select one or more blocks to save as a preset.'],
            [['blocks' => [self::HEADING, '0193a5be-0000-7000-8000-0000000000ff'], 'name' => 'Promo'], 'There is no block 0193a5be-0000-7000-8000-0000000000ff in the tree.'],
            [['blocks' => [self::HEADING], 'name' => 'Promo', 'category' => ['Campaigns']], null],
            [['name' => 'Promo'], 'The request needs the keys of the blocks to save, or the whole tree.'],
        ] as [$body, $error]) {
            self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), ...$body], $csrfToken);

            self::assertResponseStatusCodeSame(400, json_encode($body, \JSON_THROW_ON_ERROR));
            if (null !== $error) {
                self::assertSame(['error' => $error], json_decode((string) $client->getResponse()->getContent(), true));
            }
        }

        self::assertSame([], self::editorPresetIds($client, $page->getUuid()));
    }

    /**
     * Saving, replacing, renaming and inserting each answer a request without a body, or whose body names no entry, with
     * why
     *
     * @test
     */
    public function it_refuses_a_request_without_a_body_or_whose_body_names_no_entry(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);
        $uuid = self::savePreset($client, $csrfToken, $page->getUuid(), [self::HEADING], 'Promo');

        foreach ([
            ['POST', '/admin/cms/api/presets'],
            ['PUT', sprintf('/admin/cms/api/presets/%s', $uuid)],
            ['PATCH', sprintf('/admin/cms/api/presets/%s', $uuid)],
            ['POST', sprintf('/admin/cms/api/presets/%s/insert', $uuid)],
        ] as [$method, $uri]) {
            self::assertSame(['error' => 'Request body is empty.'], self::json($client, $method, $uri, null, $csrfToken), sprintf('%s %s', $method, $uri));
            self::assertResponseStatusCodeSame(400);
        }

        self::assertSame(['error' => 'The request needs the entry the blocks are in.'], self::json($client, 'POST', '/admin/cms/api/presets', ['blocks' => [self::HEADING], 'name' => 'Promo'], $csrfToken));
        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'The request needs the entry the blocks are in.'], self::json($client, 'PUT', sprintf('/admin/cms/api/presets/%s', $uuid), ['blocks' => [self::HEADING]], $csrfToken));
        self::assertResponseStatusCodeSame(400);
        self::assertSame([$uuid], self::editorPresetIds($client, $page->getUuid()));
    }

    /**
     * @test
     */
    public function it_refuses_to_save_a_preset_without_the_builder_s_csrf_token(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());

        self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), 'blocks' => [self::HEADING], 'name' => 'Promo']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @test
     */
    public function it_replaces_an_editor_preset_s_blocks_keeping_its_name_and_category_and_leaves_the_entries_it_was_inserted_into_as_they_are(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);
        $uuid = self::savePreset($client, $csrfToken, $page->getUuid(), [self::HEADING], 'Promo', 'Campaigns');

        // Inserted into another page, and saved there
        $inserted = self::json($client, 'POST', sprintf('/admin/cms/api/presets/%s/insert', $uuid), ['entry' => $page->getUuid()]);
        self::assertIsArray($inserted);
        self::assertIsArray($inserted['blocks'] ?? null);
        self::assertIsArray($inserted['blocks'][0] ?? null);
        self::assertIsString($inserted['blocks'][0]['key'] ?? null);
        $other = self::createPageWithBlocks('Contact', 'contact', [self::heading($inserted['blocks'][0]['key'], 'Winter sale')]);

        self::mockTime('2026-11-21 09:30:00');
        $response = self::json($client, 'PUT', sprintf('/admin/cms/api/presets/%s', $uuid), ['entry' => $page->getUuid(), 'blocks' => [self::RICH_TEXT, self::COLUMNS]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['preset' => ['id' => $uuid, 'name' => 'Promo', 'category' => 'Campaigns', 'description' => null, 'changedAt' => '2026-11-21T09:30:00Z']], $response);
        $listed = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $other->getUuid()))[$uuid] ?? null;
        self::assertIsArray($listed);
        self::assertSame(['columns', 'rich_text'], array_column($listed['blocks'], 'type'));
        self::assertSame(['heading'], array_map(static fn (StoredBlock $block): string => $block->type, self::reloadPage($other)->getBlockTree()->blocks));
    }

    /**
     * @test
     */
    public function it_refuses_to_replace_a_code_preset_or_one_that_does_not_exist(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PUT', '/admin/cms/api/presets/0193a5be-0000-7000-8000-00000000ffff', ['entry' => $page->getUuid(), 'blocks' => [self::HEADING]], $csrfToken);
        self::assertResponseStatusCodeSame(404);

        // Code presets are read-only
        self::json($client, 'PUT', '/admin/cms/api/presets/hero', ['entry' => $page->getUuid(), 'blocks' => [self::HEADING]], $csrfToken);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_renames_and_recategorises_an_editor_preset_at_once_bumping_its_changed_at(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);
        $uuid = self::savePreset($client, $csrfToken, $page->getUuid(), [self::HEADING], 'Promo', 'Campaigns');

        self::mockTime('2026-11-20 10:05:00');
        $renamed = self::json($client, 'PATCH', sprintf('/admin/cms/api/presets/%s', $uuid), ['name' => 'Spring promo'], $csrfToken);
        $recategorised = self::json($client, 'PATCH', sprintf('/admin/cms/api/presets/%s', $uuid), ['category' => 'Seasonal', 'description' => 'For the spring sale'], $csrfToken);

        self::assertSame(['preset' => ['id' => $uuid, 'name' => 'Spring promo', 'category' => 'Campaigns', 'description' => null, 'changedAt' => '2026-11-20T10:05:00Z']], $renamed);
        self::assertSame(['preset' => ['id' => $uuid, 'name' => 'Spring promo', 'category' => 'Seasonal', 'description' => 'For the spring sale', 'changedAt' => '2026-11-20T10:05:00Z']], $recategorised);

        // Without a category
        self::json($client, 'PATCH', sprintf('/admin/cms/api/presets/%s', $uuid), ['category' => ''], $csrfToken);
        $listed = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()))[$uuid] ?? null;
        self::assertIsArray($listed);
        self::assertSame(['Spring promo', null, ['heading']], [$listed['name'], $listed['category'], array_column($listed['blocks'], 'type')]);

        self::json($client, 'PATCH', sprintf('/admin/cms/api/presets/%s', $uuid), ['name' => ''], $csrfToken);
        self::assertResponseStatusCodeSame(400);
        self::json($client, 'PATCH', sprintf('/admin/cms/api/presets/%s', $uuid), ['name' => 'Without a token']);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @test
     */
    public function it_deletes_an_editor_preset_leaving_the_entries_it_was_inserted_into_as_they_are(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);
        $uuid = self::savePreset($client, $csrfToken, $page->getUuid(), [self::HEADING], 'Promo');

        self::json($client, 'DELETE', sprintf('/admin/cms/api/presets/%s', $uuid), null, $csrfToken);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::editorPresetIds($client, $page->getUuid()));
        self::assertCount(3, self::reloadPage($page)->getBlockTree()->blocks);

        self::json($client, 'DELETE', sprintf('/admin/cms/api/presets/%s', $uuid), null, $csrfToken);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_downloads_an_editor_preset_as_a_code_preset_file_named_by_its_name(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', [...self::blocks(), new StoredBlock('0193a5be-0000-7000-8000-000000000006', 'image', 1, fields: ['image' => '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', 'caption' => ['en_US' => 'Our shop']])]);
        $uuid = self::savePreset($client, self::openBuilder($client, $page), $page->getUuid(), [self::HEADING, '0193a5be-0000-7000-8000-000000000006'], 'Spring promo', 'Campaigns');

        $client->request('GET', sprintf('/admin/cms/api/presets/%s/export', $uuid));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=spring_promo.json');
        $content = (string) $client->getResponse()->getContent();
        self::assertStringEndsWith("}\n", $content);
        /** @var array{blocks: list<array{fields: array<string, mixed>}>} $file */
        $file = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['format', 'name', 'category', 'description', 'thumbnail', 'startingPoint', 'blocks'], array_keys($file));
        self::assertSame(['caption' => ['en_US' => 'Our shop']], $file['blocks'][1]['fields']);

        $client->request('GET', '/admin/cms/api/presets/0193a5be-0000-7000-8000-00000000ffff/export');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<string> $blocks
     *
     * @return string the new preset's UUID
     */
    private static function savePreset(KernelBrowser $client, string $csrfToken, string $entry, array $blocks, string $name, ?string $category = null): string
    {
        $response = self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $entry, 'blocks' => $blocks, 'name' => $name, 'category' => $category], $csrfToken);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($response);
        self::assertIsArray($response['preset'] ?? null);
        self::assertIsString($response['preset']['id'] ?? null);

        return $response['preset']['id'];
    }

    /**
     * The ids of the editor presets the Presets tab lists
     *
     * @return list<string>
     */
    private static function editorPresetIds(KernelBrowser $client, string $entry): array
    {
        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $entry));

        return array_values(array_map(static fn (array $preset): string => $preset['id'], array_filter($presets, static fn (array $preset): bool => !$preset['fromCode'])));
    }

    /**
     * Heading, Columns (Column: Button), Rich text
     *
     * @return list<StoredBlock>
     */
    private static function blocks(): array
    {
        return [
            self::heading(self::HEADING, 'Winter sale'),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: [
                new StoredBlock(self::COLUMN, 'column', 1, children: [new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'url', 'ref' => '/sale', 'anchor' => null, 'label' => ['en_US' => 'Shop now']]])]),
            ]),
            new StoredBlock(self::RICH_TEXT, 'rich_text', 1),
        ];
    }
}
