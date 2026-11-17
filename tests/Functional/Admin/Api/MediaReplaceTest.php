<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\MediaItemReplaced;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Replacing a media item's file keeps its UUID, alt text and focal point, so the pages that use it show the new file
 * without being published again. The new file is checked like an upload and must be of the same kind. See "Replacing a
 * file" in docs/spec/media.md and ADR 0013
 */
final class MediaReplaceTest extends MediaApiTestCase
{
    private const BLOCK = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e70';

    protected static function getKernelClass(): string
    {
        return MediaFieldsKernel::class;
    }

    /**
     * @test
     */
    public function it_replaces_the_file_keeping_the_uuid_alt_text_and_focal_point_so_a_published_page_shows_the_new_file(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem('summer.png', self::png(80, 40));
        $uuid = $mediaItem->getUuid();
        self::json($client, 'PATCH', '/admin/cms/api/media/' . $uuid, ['altTexts' => ['en_US' => 'Summer sale'], 'focalPoint' => ['x' => 0.2, 'y' => 0.7]], $config['csrfToken']);
        self::createPageWithBlocks('Sale', 'sale', [new StoredBlock(self::BLOCK, 'media_names', 1, fields: ['image' => $uuid])], published: true);
        $oldPath = $mediaItem->getPath();
        self::assertSame('/media/cms/' . $oldPath, $client->request('GET', '/en_US/sale')->filter('.cms-tree .app-media-names__file')->attr('src'));

        $answer = self::replace($client, $config['csrfToken'], $uuid, $this->uploadedFile('summer-2027.jpg', self::jpeg(120, 60)));

        self::assertResponseIsSuccessful();
        self::assertSame($uuid, $answer['uuid']);
        self::assertSame('summer', $answer['name']);
        self::assertSame('summer-2027.jpg', $answer['originalFilename']);
        self::assertSame('image/jpeg', $answer['mimeType']);
        self::assertSame(120, $answer['width']);
        self::assertSame(['en_US' => 'Summer sale'], $answer['altTexts']);
        self::assertSame(['x' => 0.2, 'y' => 0.7], $answer['focalPoint']);
        self::assertSame(1, $answer['usageCount']);

        // A new storage path, and the page shows it without being published again
        $replaced = self::reloaded($uuid);
        self::assertNotSame($oldPath, $replaced->getPath());
        self::assertStringStartsWith($uuid . '/', $replaced->getPath());
        self::assertStringEndsWith('/summer-2027.jpg', $replaced->getPath());
        self::assertSame('/media/cms/' . $replaced->getPath(), $answer['url']);
        self::assertFileExists(self::mediaDirectory() . '/' . $replaced->getPath());
        self::assertSame('/media/cms/' . $replaced->getPath(), $client->request('GET', '/en_US/sale')->filter('.cms-tree .app-media-names__file')->attr('src'));

        // The old file stays for the grace period
        self::assertFileExists(self::mediaDirectory() . '/' . $oldPath);
    }

    /**
     * @test
     */
    public function it_dispatches_media_item_replaced(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $client->disableReboot();
        $uuid = $this->uploadMediaItem('logo.png', self::png())->getUuid();

        /** @var list<MediaItemReplaced> $dispatched */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(MediaItemReplaced::class, static function (MediaItemReplaced $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        self::replace($client, $config['csrfToken'], $uuid, $this->uploadedFile('logo-2027.png', self::png(rgb: [0, 0, 0])));

        self::assertResponseIsSuccessful();
        self::assertEquals([new MediaItemReplaced($uuid)], $dispatched);
    }

    /**
     * @test
     *
     * @dataProvider refusedFiles
     */
    public function it_refuses_a_file_of_another_kind_or_one_an_upload_would_refuse(string $filename, string $content, string $error): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem('logo.png', self::png());
        $path = $mediaItem->getPath();

        $answer = self::replace($client, $config['csrfToken'], $mediaItem->getUuid(), $this->uploadedFile($filename, $content));

        self::assertResponseStatusCodeSame(400);
        self::assertIsString($answer['error'] ?? null);
        self::assertStringStartsWith($error, $answer['error']);
        self::assertSame($path, self::reloaded($mediaItem->getUuid())->getPath());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedFiles(): iterable
    {
        yield 'a document for an image' => ['prices.pdf', "%PDF-1.4\n%%EOF\n", 'prices.pdf isn\'t an image. Only an image can replace an image.'];
        // Text named as a PNG is text
        yield 'a type the library does not take' => ['logo-2027.png', "Just some words, not a picture at all.\n", 'logo-2027.png is a TXT file, which the media library doesn\'t take.'];
    }

    /**
     * @test
     */
    public function it_answers_not_found_for_a_media_item_that_does_not_exist(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        self::replace($client, $config['csrfToken'], '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', $this->uploadedFile('logo.png', self::png()));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_refuses_a_request_without_a_file(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem('logo.png', self::png());

        $client->request('POST', sprintf('/admin/cms/api/media/%s/file', $mediaItem->getUuid()), [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $config['csrfToken']]);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @test
     */
    public function it_refuses_a_request_without_the_csrf_token(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem('logo.png', self::png());

        self::replace($client, 'wrong', $mediaItem->getUuid(), $this->uploadedFile('logo-2027.png', self::png()));

        self::assertResponseStatusCodeSame(403);
        self::assertSame($mediaItem->getPath(), self::reloaded($mediaItem->getUuid())->getPath());
    }

    /**
     * Replaces the media item's file as the library does, with the file in a form
     *
     * @return array<string, mixed>
     */
    private static function replace(KernelBrowser $client, string $csrfToken, string $uuid, UploadedFile $file): array
    {
        $client->request('POST', sprintf('/admin/cms/api/media/%s/file', $uuid), [], ['file' => $file], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken]);

        /** @var array<string, mixed> $answer */
        $answer = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        return $answer;
    }

    private static function reloaded(string $uuid): MediaItemInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->clear();

        /** @var MediaItemRepositoryInterface $mediaItems */
        $mediaItems = self::getContainer()->get(MediaItemRepositoryInterface::class);
        $mediaItem = $mediaItems->findOneByUuid($uuid);
        self::assertNotNull($mediaItem);

        return $mediaItem;
    }
}
