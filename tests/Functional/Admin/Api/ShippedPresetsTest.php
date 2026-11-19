<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The code presets the plugin ships in its own presets directory, which it prepends to presets.paths: they're valid,
 * each makes content that publishes once an editor picks its images, and related_products is offered only where a
 * product is available. See "Shipped code presets" in docs/spec/layouts-and-presets.md
 */
final class ShippedPresetsTest extends BuilderApiTestCase
{
    use MediaItems;

    private const DIRECTORY = __DIR__ . '/../../../../src/Resources/presets';

    /** The block types whose image field an editor picks an image for, and that field */
    private const IMAGE_FIELDS = ['image' => 'image', 'banner' => 'image'];

    /**
     * @test
     */
    public function setono_cms_preset_validate_passes_on_the_shipped_directory(): void
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:preset:validate'));

        $tester->execute(['paths' => [self::DIRECTORY]], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('[OK] 11 preset files are valid.', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * @test
     */
    public function the_block_picker_offers_the_shipped_presets_before_the_project_s_own_with_their_names_translated(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()));

        self::assertSame(
            ['banner_carousel', 'bestsellers_carousel', 'campaign_with_fallback', 'faq', 'footer_links', 'hero', 'image_and_text', 'related_products', 'three_usps', 'column_pair', 'product_spotlight', 'sale_hero'],
            array_keys($presets),
        );
        self::assertSame(['Hero', 'Banners', 'A full-width banner with a heading, text and two buttons, for the top of a page'], [$presets['hero']['name'], $presets['hero']['category'], $presets['hero']['description']]);
        self::assertSame(['Three USPs', 'Content'], [$presets['three_usps']['name'], $presets['three_usps']['category']]);
    }

    /**
     * @test
     *
     * @dataProvider provideBlockPresetsForPages
     */
    public function inserting_a_block_preset_into_a_new_page_gives_a_tree_that_publishes_once_its_images_are_picked(string $code): void
    {
        $client = self::signedIn();
        $page = self::createPage('Spring', 'spring');
        $csrfToken = self::openBuilder($client, $page);

        $blocks = self::insert($client, $code, $page);
        self::save($client, $page, $csrfToken, [
            ...array_map(static fn (int $index, array $block): array => ['op' => 'add_block', 'parent' => null, 'index' => $index, 'block' => $block], array_keys($blocks), $blocks),
            ...$this->pickImages(self::denormalized($blocks)),
        ]);

        self::assertPublishes($client, $page, $csrfToken);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function provideBlockPresetsForPages(): iterable
    {
        foreach (['banner_carousel', 'hero', 'campaign_with_fallback', 'image_and_text', 'three_usps', 'faq', 'bestsellers_carousel', 'footer_links'] as $code) {
            yield $code => [$code];
        }
    }

    /**
     * @test
     */
    public function a_page_with_the_images_of_an_image_preset_not_picked_does_not_publish(): void
    {
        $client = self::signedIn();
        $page = self::createPage('Spring', 'spring');
        $csrfToken = self::openBuilder($client, $page);

        $blocks = self::insert($client, 'three_usps', $page);
        self::save($client, $page, $csrfToken, [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => $blocks[0]]]);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @test
     */
    public function related_products_is_not_offered_in_a_page_as_it_needs_a_product(): void
    {
        $client = self::signedIn();
        $page = self::createPage('Spring', 'spring');

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()));

        self::assertFalse($presets['related_products']['fits']);
        self::assertSame(['Needs: Product page'], $presets['related_products']['reasons']);
        self::assertTrue($presets['bestsellers_carousel']['fits']);
    }

    /**
     * @test
     */
    public function related_products_is_offered_in_a_snippet_for_product_pages_and_publishes_there(): void
    {
        $client = self::signedIn();
        $snippet = self::createSnippet('Related', context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $snippet->getUuid()));
        self::assertTrue($presets['related_products']['fits']);

        $blocks = self::insert($client, 'related_products', $snippet);
        self::save($client, $snippet, $csrfToken, [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => $blocks[0]]]);

        self::assertPublishes($client, $snippet, $csrfToken);
    }

    /**
     * @test
     *
     * @dataProvider provideStartingPoints
     */
    public function a_page_created_from_a_shipped_starting_point_publishes_once_its_images_are_picked(string $code, string $layout): void
    {
        $client = self::signedIn();
        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Spring',
            'setono_sylius_cms_create_entry[slug]' => 'spring',
            'setono_sylius_cms_create_entry[startingPoint]' => $code,
        ]);
        $page = self::page('spring');
        self::assertSame($layout, $page->getBlockTree()->layout?->code);
        self::assertNotSame([], $page->getBlockTree()->blocks);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, $csrfToken, $this->pickImages($page->getBlockTree()->blocks));

        self::assertPublishes($client, $page, $csrfToken);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function provideStartingPoints(): iterable
    {
        yield 'landing_page' => ['landing_page', 'standard'];
        yield 'content_page' => ['content_page', 'standard'];
    }

    /**
     * The preset's blocks as preset_insert answers them, ready to insert into the entry
     *
     * @return list<array<string, mixed>>
     */
    private static function insert(KernelBrowser $client, string $code, EntryInterface $entry): array
    {
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/presets/%s/insert', $code), ['entry' => $entry->getUuid()]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame([], $response['notices'] ?? null);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertNotSame([], $response['blocks']);

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $response['blocks'];

        return $blocks;
    }

    /**
     * Autosaves the changes onto the entry's working copy, as the builder does
     *
     * @param list<array<string, mixed>> $changes
     */
    private static function save(KernelBrowser $client, EntryInterface $entry, string $csrfToken, array $changes): void
    {
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $entry->getUuid());
        self::assertIsArray($response);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $entry->getUuid(), ['lockToken' => $response['lockToken'] ?? null, 'changes' => $changes], $csrfToken);
        self::assertResponseIsSuccessful();
    }

    private static function assertPublishes(KernelBrowser $client, EntryInterface $entry, string $csrfToken): void
    {
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $entry->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful((string) json_encode($response));
    }

    /**
     * The changes that pick an image for every image field in the blocks, as an editor does in the builder
     *
     * @param list<StoredBlock> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function pickImages(array $blocks): array
    {
        $image = $this->uploadMediaItem('photo.png', self::png());

        $changes = [];
        foreach (self::blocksIn($blocks) as $block) {
            if (isset(self::IMAGE_FIELDS[$block->type])) {
                $changes[] = ['op' => 'set_value', 'key' => $block->key, 'field' => self::IMAGE_FIELDS[$block->type], 'locale' => null, 'value' => $image->getUuid()];
            }
        }

        return $changes;
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<StoredBlock> the blocks and every block inside them
     */
    private static function blocksIn(array $blocks): array
    {
        $all = [];
        foreach ($blocks as $block) {
            $all = [...$all, $block, ...self::blocksIn($block->children)];
        }

        return $all;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<StoredBlock>
     */
    private static function denormalized(array $blocks): array
    {
        $serializer = new StoredTreeSerializer();

        return array_map($serializer->denormalizeBlock(...), $blocks);
    }

    private static function page(string $slug): PageInterface
    {
        /** @var RepositoryInterface<PageInterface> $pages */
        $pages = self::getContainer()->get('setono_sylius_cms.repository.page');
        foreach ($pages->findAll() as $page) {
            if ($page->getTranslation('en_US')->getSlug() === $slug) {
                return $page;
            }
        }

        self::fail(sprintf('There is no page with the slug "%s"', $slug));
    }
}
