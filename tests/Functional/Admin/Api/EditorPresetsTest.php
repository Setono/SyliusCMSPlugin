<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Editors save blocks as presets of their own from the builder, replace their blocks, rename and recategorise them, and
 * delete them. Saving takes effect at once, and never changes the entries a preset was inserted into. See "Editor
 * presets" in docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
 */
final class EditorPresetsTest extends BuilderApiTestCase
{
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
        self::assertNotSame(self::COLUMNS, ($response['blocks'][0] ?? [])['key'] ?? null);
    }

    /**
     * @test
     */
    public function it_refuses_a_preset_without_a_name_without_blocks_or_with_a_block_the_entry_does_not_have(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', self::blocks());
        $csrfToken = self::openBuilder($client, $page);

        foreach ([
            ['blocks' => [self::HEADING], 'name' => '  '],
            ['blocks' => [self::HEADING]],
            ['blocks' => [], 'name' => 'Promo'],
            ['blocks' => ['0193a5be-0000-7000-8000-0000000000ff'], 'name' => 'Promo'],
            ['blocks' => [self::HEADING], 'name' => 'Promo', 'category' => ['Campaigns']],
        ] as $body) {
            self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), ...$body], $csrfToken);

            self::assertResponseStatusCodeSame(400, json_encode($body, \JSON_THROW_ON_ERROR));
        }

        self::assertSame([], self::editorPresetIds($client, $page->getUuid()));
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
