<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\BeforeMediaItemDelete;
use Setono\SyliusCMSPlugin\Event\MediaItemDeleted;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Each media item's Used in list comes from the usage index, and a media item can't be deleted while it lists a use.
 * Otherwise a listener can veto the delete, and the media item and its file are deleted. See "Usage index" and
 * "Deleting" in docs/spec/media.md
 */
final class MediaDeleteTest extends MediaApiTestCase
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
            ['entry' => $sale->getUuid(), 'title' => 'Summer sale', 'contentType' => 'Page', 'url' => '/admin/cms/entry/' . $sale->getUuid(), 'draft' => true, 'published' => true],
            ['entry' => $story->getUuid(), 'title' => 'Our story', 'contentType' => 'Page', 'url' => '/admin/cms/entry/' . $story->getUuid(), 'draft' => true, 'published' => false],
        ]], $answer);

        // The library counts them
        $list = self::json($client, 'GET', '/admin/cms/api/media');
        self::assertIsArray($list);
        self::assertSame(['unused' => 0, 'summer' => 2], array_column($list['items'], 'usageCount', 'name'));
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
        self::assertSame(['error' => 'It\'s used in an entry, so it can\'t be deleted.'], $answer);
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
