<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\BeforeMediaItemDelete;
use Setono\SyliusCMSPlugin\Event\MediaItemDeleted;
use Setono\SyliusCMSPlugin\Preset\EditorPresetDeleterInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Each media item's Used in list, the usage counts and the unused filter come from the usage index, and a media item
 * can't be deleted while it lists a use. Otherwise a listener can veto the delete, and the media item and its file are
 * deleted. See "Usage index", "Search and filters" and "Deleting" in docs/spec/media.md
 */
final class MediaUsageTest extends MediaApiTestCase
{
    private const BLOCK = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e70';

    protected static function getKernelClass(): string
    {
        return MediaFieldsKernel::class;
    }

    /**
     * @test
     */
    public function it_lists_the_entries_that_use_a_media_item_with_whether_their_draft_and_published_content_do(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $uuid = $this->uploadMediaItem('summer.png', self::png())->getUuid();
        $sale = self::createPageWithBlocks('Summer sale', 'sale', [self::block($uuid)], published: true);
        $story = self::createPageWithBlocks('Our story', 'story', [self::block($uuid)]);
        $this->uploadMediaItem('unused.png', self::png(rgb: [0, 0, 0]));

        $answer = self::json($client, 'GET', sprintf('/admin/cms/api/media/%s/uses', $uuid));

        self::assertResponseIsSuccessful();
        self::assertSame(['uses' => [
            ['entry' => $sale->getUuid(), 'preset' => null, 'title' => 'Summer sale', 'contentType' => 'Page', 'url' => '/admin/cms/entry/' . $sale->getUuid(), 'draft' => true, 'published' => true],
            ['entry' => $story->getUuid(), 'preset' => null, 'title' => 'Our story', 'contentType' => 'Page', 'url' => '/admin/cms/entry/' . $story->getUuid(), 'draft' => true, 'published' => false],
        ]], $answer);

        // The library counts them
        self::assertSame(['unused' => 0, 'summer' => 2], array_column(self::items($client, ''), 'usageCount', 'name'));
    }

    /**
     * @test
     */
    public function it_filters_the_media_items_nothing_uses(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $used = $this->uploadMediaItem('used.png', self::png());
        $draftOnly = $this->uploadMediaItem('draft-only.png', self::png(rgb: [0, 0, 0]));
        $unused = $this->uploadMediaItem('unused.png', self::png(rgb: [0, 200, 0]));
        $prices = $this->uploadMediaItem('prices.pdf', "%PDF-1.4\n%%EOF\n");
        self::createPageWithBlocks('Summer sale', 'sale', [self::block($used->getUuid())], published: true);
        self::createPageWithBlocks('Our story', 'story', [self::block($draftOnly->getUuid())]);

        self::assertSame([$prices->getUuid(), $unused->getUuid()], array_column(self::items($client, 'unused=1'), 'uuid'));

        // With the other filters
        self::assertSame([$unused->getUuid()], array_column(self::items($client, 'unused=1&kind=image'), 'uuid'));
    }

    /**
     * The media items the library lists with this query
     *
     * @return list<array<string, mixed>>
     */
    private static function items(KernelBrowser $client, string $query): array
    {
        $answer = self::json($client, 'GET', '/admin/cms/api/media?' . $query);
        self::assertResponseIsSuccessful();
        self::assertIsArray($answer);
        self::assertIsArray($answer['items']);

        /** @var list<array<string, mixed>> $items */
        $items = $answer['items'];

        return $items;
    }

    /**
     * @test
     */
    public function it_refuses_to_delete_a_media_item_while_it_is_used_saying_how_many_entries_use_it(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem('summer.png', self::png());
        $uuid = $mediaItem->getUuid();
        $page = self::createPageWithBlocks('Summer sale', 'sale', [self::block($uuid)], published: true);

        $answer = self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'It\'s used in one place, so it can\'t be deleted.'], $answer);
        self::assertTrue(self::exists($uuid));
        self::assertFileExists(self::mediaDirectory() . '/' . $mediaItem->getPath());

        // Taking it out of the draft leaves it in the published content
        $page = self::reloadPage($page);
        self::saveChanges($page, [['op' => 'set_value', 'key' => self::BLOCK, 'field' => 'image', 'locale' => null, 'value' => null]]);
        self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);
        self::assertResponseStatusCodeSame(409);

        // Once that's published, nothing uses it
        self::publishEntry(self::reloadPage($page));
        self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @test
     */
    public function it_counts_the_media_items_an_editor_preset_uses_so_one_can_not_be_deleted_while_a_preset_uses_it(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $uuid = $this->uploadMediaItem('summer.png', self::png())->getUuid();
        $this->uploadMediaItem('unused.png', self::png(rgb: [0, 0, 0]));
        $preset = self::presetSaver()->save(new Preset('Summer banner', [self::block($uuid)], 'Campaigns'));

        self::assertSame(['uses' => [
            ['entry' => null, 'preset' => $preset->uuid, 'title' => 'Summer banner', 'contentType' => 'Preset', 'url' => sprintf('/admin/cms/presets?tab=block_presets#cms-preset-%s', (string) $preset->uuid), 'draft' => false, 'published' => false],
        ]], self::json($client, 'GET', sprintf('/admin/cms/api/media/%s/uses', $uuid)));
        self::assertSame(['unused' => 0, 'summer' => 1], array_column(self::items($client, ''), 'usageCount', 'name'));
        self::assertSame(['unused'], array_column(self::items($client, 'unused=1'), 'name'));

        $answer = self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'It\'s used in one place, so it can\'t be deleted.'], $answer);

        // Its blocks replaced by ones that don't use it, nothing uses it
        $replaced = self::presetSaver()->save(new Preset('Summer banner', [], uuid: $preset->uuid));
        self::assertSame(['uses' => []], self::json($client, 'GET', sprintf('/admin/cms/api/media/%s/uses', $uuid)));

        // Used again, the preset's delete takes its use with it
        self::presetSaver()->save(new Preset('Summer banner', [self::block($uuid)], uuid: $replaced->uuid));
        self::assertSame(['uses' => [['entry' => null, 'preset' => $replaced->uuid]]], self::uses($client, $uuid));
        /** @var EditorPresetDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EditorPresetDeleterInterface::class);
        $deleter->delete((string) $preset->uuid);

        self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * The preset's content can't be read, as when a newer version of the plugin wrote it, so it's listed without a name
     *
     * @test
     */
    public function it_lists_an_editor_preset_whose_content_can_not_be_read_without_its_name(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $uuid = $this->uploadMediaItem('summer.png', self::png())->getUuid();
        $preset = self::presetSaver()->save(new Preset('Summer banner', [self::block($uuid)], startingPoint: new StartingPoint('page', null)));
        /** @var EditorPresetRepositoryInterface $presets */
        $presets = self::getContainer()->get('setono_sylius_cms.repository.editor_preset');
        $row = $presets->findOneByUuid((string) $preset->uuid);
        self::assertNotNull($row);
        $row->setContent('{"format": 2}');
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.editor_preset');
        $manager->flush();

        self::assertSame(['uses' => [
            ['entry' => null, 'preset' => $preset->uuid, 'title' => null, 'contentType' => 'Preset', 'url' => sprintf('/admin/cms/presets?tab=block_presets#cms-preset-%s', (string) $preset->uuid), 'draft' => false, 'published' => false],
        ]], self::json($client, 'GET', sprintf('/admin/cms/api/media/%s/uses', $uuid)));
    }

    /**
     * @test
     */
    public function it_lists_an_entry_and_a_preset_that_use_a_media_item_and_counts_both(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $uuid = $this->uploadMediaItem('summer.png', self::png())->getUuid();
        $page = self::createPageWithBlocks('Summer sale', 'sale', [self::block($uuid)], published: true);
        $preset = self::presetSaver()->save(new Preset('Summer banner', [self::block($uuid)]));

        self::assertSame(['uses' => [['entry' => $page->getUuid(), 'preset' => null], ['entry' => null, 'preset' => $preset->uuid]]], self::uses($client, $uuid));
        self::assertSame(['summer' => 2], array_column(self::items($client, ''), 'usageCount', 'name'));
        self::assertSame(['error' => 'It\'s used in 2 places, so it can\'t be deleted.'], self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']));
    }

    /**
     * @test
     */
    public function it_deletes_a_media_item_nothing_uses_with_its_file_dispatching_before_media_item_delete_and_media_item_deleted(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $client->disableReboot();
        $mediaItem = $this->uploadMediaItem('summer.png', self::png());
        $uuid = $mediaItem->getUuid();
        $file = self::mediaDirectory() . '/' . $mediaItem->getPath();

        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(BeforeMediaItemDelete::class, static function (BeforeMediaItemDelete $event) use (&$dispatched): void {
            $dispatched[] = 'before ' . $event->mediaItem->getUuid();
        });
        $eventDispatcher->addListener(MediaItemDeleted::class, static function (MediaItemDeleted $event) use (&$dispatched, $file): void {
            $dispatched[] = sprintf('deleted %s, file %s', $event->uuid, is_file($file) ? 'kept' : 'gone');
        });

        self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['before ' . $uuid, sprintf('deleted %s, file gone', $uuid)], $dispatched);
        self::assertFalse(self::exists($uuid));
        self::assertFileDoesNotExist($file);
    }

    /**
     * @test
     */
    public function it_keeps_a_media_item_whose_delete_a_listener_vetoes_and_shows_its_message(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $client->disableReboot();
        $mediaItem = $this->uploadMediaItem('logo.png', self::png());
        $uuid = $mediaItem->getUuid();

        $deleted = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(BeforeMediaItemDelete::class, static fn (BeforeMediaItemDelete $event) => $event->veto('The logo stays until the rebrand'));
        $eventDispatcher->addListener(MediaItemDeleted::class, static function (MediaItemDeleted $event) use (&$deleted): void {
            $deleted[] = $event;
        });

        $answer = self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, $config['csrfToken']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'The logo stays until the rebrand'], $answer);
        self::assertSame([], $deleted);
        self::assertTrue(self::exists($uuid));
        self::assertFileExists(self::mediaDirectory() . '/' . $mediaItem->getPath());
    }

    /**
     * @test
     */
    public function it_refuses_a_delete_without_the_csrf_token(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $uuid = $this->uploadMediaItem('logo.png', self::png())->getUuid();

        self::json($client, 'DELETE', '/admin/cms/api/media/' . $uuid, null, 'wrong');

        self::assertResponseStatusCodeSame(403);
        self::assertTrue(self::exists($uuid));
    }

    /**
     * The media item's Used in list, each use as its entry's and its preset's UUID
     *
     * @return array{uses: list<array{entry: mixed, preset: mixed}>}
     */
    private static function uses(KernelBrowser $client, string $uuid): array
    {
        $answer = self::json($client, 'GET', sprintf('/admin/cms/api/media/%s/uses', $uuid));
        self::assertIsArray($answer);
        self::assertIsArray($answer['uses'] ?? null);

        $uses = [];
        foreach ($answer['uses'] as $use) {
            self::assertIsArray($use);
            $uses[] = ['entry' => $use['entry'] ?? null, 'preset' => $use['preset'] ?? null];
        }

        return ['uses' => $uses];
    }

    private static function presetSaver(): EditorPresetSaverInterface
    {
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);

        return $saver;
    }

    private static function block(string $image): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'media_names', 1, fields: ['image' => $image]);
    }

    private static function exists(string $uuid): bool
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->clear();

        /** @var MediaItemRepositoryInterface $mediaItems */
        $mediaItems = self::getContainer()->get(MediaItemRepositoryInterface::class);

        return null !== $mediaItems->findOneByUuid($uuid);
    }
}
