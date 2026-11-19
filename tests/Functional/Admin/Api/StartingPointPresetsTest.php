<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Editors save an entry's whole tree, from the builder's entry settings, as a starting point for the entry's content
 * type, with its layout and the layout's field values, and replace an editor starting point's tree the same way. See
 * "Editor presets" and "Starting points" in docs/spec/layouts-and-presets.md and "JSON endpoints" in
 * docs/spec/builder.md
 */
final class StartingPointPresetsTest extends BuilderApiTestCase
{
    use StoreLocations;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const COLUMNS = '0193a5be-0000-7000-8000-000000000002';

    private const COLUMN = '0193a5be-0000-7000-8000-000000000003';

    /**
     * @test
     */
    public function it_saves_the_whole_tree_of_the_working_copy_as_a_starting_point_for_the_entrys_content_type_with_its_layout(): void
    {
        $client = self::signedIn();
        $page = self::campaignPage();
        $csrfToken = self::openBuilder($client, $page);

        $uuid = self::createdId(self::json($client, 'POST', '/admin/cms/api/presets', [
            'entry' => $page->getUuid(),
            'startingPoint' => true,
            'name' => 'Campaign page',
            'category' => 'Pages',
        ], $csrfToken));

        self::assertResponseStatusCodeSame(201);
        $saved = self::preset($uuid);
        self::assertEquals(new StartingPoint('page', new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Up to 50% off'], 'transparentHeader' => true], ['tagline' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => 'abc']]])), $saved->startingPoint);
        self::assertSame([self::HEADING, self::COLUMNS], array_map(static fn (StoredBlock $block): string => $block->key, $saved->blocks));
        self::assertSame([self::COLUMNS, self::COLUMN], $saved->blocks[1]->keys());
        self::assertSame(['Campaign page', 'Pages'], [$saved->name, $saved->category]);

        // It isn't a block preset, so the Presets tab doesn't list it, and the answer lists it among the page's starting points
        $index = self::json($client, 'GET', sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()));
        self::assertIsArray($index);
        self::assertArrayNotHasKey($uuid, self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid())));
        self::assertIsArray($index['startingPoints'] ?? null);
        // After the plugin's shipped starting points for pages
        self::assertSame(['content_page', 'landing_page', $uuid], array_column($index['startingPoints'], 'id'));
        self::assertSame(['id' => $uuid, 'fromCode' => false, 'name' => 'Campaign page', 'category' => 'Pages', 'description' => null], $index['startingPoints'][2] ?? null);
    }

    /**
     * @test
     */
    public function it_lists_only_the_starting_points_of_the_entrys_content_type(): void
    {
        $client = self::signedIn();
        $page = self::campaignPage();
        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), 'startingPoint' => true, 'name' => 'Campaign page'], $csrfToken);

        $snippet = self::createSnippet('Footer', [self::heading(self::HEADING, 'Need help?')]);
        $index = self::json($client, 'GET', sprintf('/admin/cms/api/presets?entry=%s', $snippet->getUuid()));

        self::assertIsArray($index);
        self::assertSame([], $index['startingPoints'] ?? null);
    }

    /**
     * @test
     */
    public function it_replaces_an_editor_starting_points_tree_and_layout_keeping_its_name(): void
    {
        $client = self::signedIn();
        $page = self::campaignPage();
        $csrfToken = self::openBuilder($client, $page);
        $uuid = self::createdId(self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), 'startingPoint' => true, 'name' => 'Campaign page'], $csrfToken));

        $other = self::createPageWithBlocks('Contact', 'contact-us', [self::heading('0193a5be-0000-7000-8000-0000000000aa', 'Contact us')]);
        self::json($client, 'PUT', sprintf('/admin/cms/api/presets/%s', $uuid), ['entry' => $other->getUuid(), 'startingPoint' => true], $csrfToken);

        self::assertResponseIsSuccessful();
        $replaced = self::preset($uuid);
        self::assertSame('Campaign page', $replaced->name);
        self::assertEquals(new StartingPoint('page', new StoredLayout('standard', 1)), $replaced->startingPoint);
        self::assertSame(['0193a5be-0000-7000-8000-0000000000aa'], array_map(static fn (StoredBlock $block): string => $block->key, $replaced->blocks));
    }

    /**
     * @test
     */
    public function it_keeps_a_block_preset_a_block_preset_and_a_starting_point_a_starting_point_of_its_content_type_when_replacing(): void
    {
        $client = self::signedIn();
        $page = self::campaignPage();
        $csrfToken = self::openBuilder($client, $page);
        $startingPoint = self::createdId(self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), 'startingPoint' => true, 'name' => 'Campaign page'], $csrfToken));
        $blockPreset = self::createdId(self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $page->getUuid(), 'blocks' => [self::HEADING], 'name' => 'Heading'], $csrfToken));
        $snippet = self::createSnippet('Footer', [self::heading('0193a5be-0000-7000-8000-0000000000bb', 'Need help?')]);

        foreach ([
            [$startingPoint, ['entry' => $page->getUuid(), 'blocks' => [self::HEADING]]],
            [$startingPoint, ['entry' => $snippet->getUuid(), 'startingPoint' => true]],
            [$blockPreset, ['entry' => $page->getUuid(), 'startingPoint' => true]],
        ] as [$uuid, $body]) {
            self::json($client, 'PUT', sprintf('/admin/cms/api/presets/%s', $uuid), $body, $csrfToken);

            self::assertResponseStatusCodeSame(400, json_encode($body, \JSON_THROW_ON_ERROR));
        }

        self::assertSame('page', self::preset($startingPoint)->startingPoint?->contentType);
        self::assertNull(self::preset($blockPreset)->startingPoint);
    }

    /**
     * @test
     */
    public function it_refuses_a_starting_point_of_an_entry_without_blocks_or_of_a_content_type_without_a_block_tree(): void
    {
        $client = self::signedIn();
        $empty = self::createPage('About us', 'about-us');
        $store = self::createStoreLocation('Flagship store', 'flagship', 'Copenhagen');

        foreach ([$empty, $store] as $entry) {
            $csrfToken = self::openBuilder($client, $entry);
            self::json($client, 'POST', '/admin/cms/api/presets', ['entry' => $entry->getUuid(), 'startingPoint' => true, 'name' => 'Starting point'], $csrfToken);

            self::assertResponseStatusCodeSame(400);
        }

        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);
        self::assertSame([], array_filter($presets->all(), static fn (Preset $preset): bool => !$preset->isFromCode()));
    }

    /**
     * A page on the landing layout, with a tagline and its translation state, holding a heading and columns
     */
    private static function campaignPage(): PageInterface
    {
        $page = self::createPageWithBlocks('Summer sale', 'summer-sale', [
            self::heading(self::HEADING, 'Summer sale'),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: [new StoredBlock(self::COLUMN, 'column', 1)]),
        ]);
        $page->setBlockTree(new StoredTree(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Up to 50% off'], 'transparentHeader' => true], ['tagline' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => 'abc']]]), $page->getBlockTree()->blocks));
        self::entityManager()->flush();

        return $page;
    }

    /**
     * The UUID of the preset preset_create answered with
     */
    private static function createdId(mixed $response): string
    {
        self::assertIsArray($response);
        self::assertIsArray($response['preset'] ?? null);
        self::assertIsString($response['preset']['id'] ?? null);

        return $response['preset']['id'];
    }

    private static function preset(string $uuid): Preset
    {
        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);
        $preset = $presets->find($uuid);
        self::assertNotNull($preset);

        return $preset;
    }
}
