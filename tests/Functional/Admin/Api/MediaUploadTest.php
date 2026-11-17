<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Media\FileSize;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;

/**
 * Editors upload images, videos and documents into the open folder. Each file is checked against media.types and the
 * upload limit, and what it is is read from the file itself. See "Uploading" in docs/spec/media.md and media_upload in
 * docs/spec/builder.md
 */
final class MediaUploadTest extends MediaApiTestCase
{
    /**
     * @test
     */
    public function it_uploads_many_files_at_once_into_the_open_folder_and_reads_what_they_are_from_the_files_themselves(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $campaigns = self::createMediaFolder('Campaigns');

        $answer = self::upload($client, $config['csrfToken'], [
            $this->uploadedFile('Summer Sale.png', self::png(40, 20)),
            // A JPEG named as a PDF is a JPEG
            $this->uploadedFile('team.pdf', self::jpeg(30, 60)),
            $this->uploadedFile('price-list.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n"),
        ], $campaigns->getUuid());

        self::assertSame([], $answer['refused']);
        self::assertSame([], $answer['duplicates']);
        self::assertCount(3, $answer['created']);
        [$sale, $team, $prices] = $answer['created'];

        self::assertSame(['uuid', 'kind', 'name', 'originalFilename', 'mimeType', 'size', 'width', 'height', 'credit', 'altTexts', 'missingAlt', 'focalPoint', 'poster', 'folder', 'url', 'usageCount', 'createdAt', 'changedAt'], array_keys($sale));
        self::assertSame(0, $sale['usageCount']);
        self::assertSame('image', $sale['kind']);
        self::assertSame('Summer Sale', $sale['name']);
        self::assertSame('Summer Sale.png', $sale['originalFilename']);
        self::assertSame('image/png', $sale['mimeType']);
        self::assertSame([40, 20], [$sale['width'], $sale['height']]);
        self::assertSame(['x' => 0.5, 'y' => 0.5], $sale['focalPoint']);
        self::assertSame([], $sale['altTexts']);
        self::assertTrue($sale['missingAlt']);
        self::assertNull($sale['credit']);
        self::assertSame($campaigns->getUuid(), $sale['folder']);

        self::assertSame('image', $team['kind']);
        self::assertSame('team', $team['name']);
        self::assertSame('image/jpeg', $team['mimeType']);
        self::assertSame([30, 60], [$team['width'], $team['height']]);
        self::assertIsString($team['url']);
        self::assertStringEndsWith('/team.jpg', $team['url']);

        self::assertSame('document', $prices['kind']);
        self::assertSame('application/pdf', $prices['mimeType']);
        self::assertNull($prices['width']);
        self::assertNull($prices['focalPoint']);
        self::assertFalse($prices['missingAlt']);

        // The file is public at its URL, and its checksum and size are the stored file's
        self::assertIsString($sale['uuid']);
        $mediaItem = self::mediaItems()->findOneByUuid($sale['uuid']);
        self::assertNotNull($mediaItem);
        self::assertIsString($sale['url']);
        $stored = self::mediaDirectory() . substr($sale['url'], strlen('/media/cms'));
        self::assertFileExists($stored);
        self::assertSame(hash_file('sha256', $stored), $mediaItem->getChecksum());
        self::assertSame(filesize($stored), $sale['size']);
    }

    /**
     * Folders exist only in the library, so moving a media item never moves its file
     *
     * @test
     */
    public function it_stores_a_file_under_a_path_built_from_the_media_items_uuid(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $answer = self::upload($client, $config['csrfToken'], [$this->uploadedFile('Summer Sale.png', self::png())]);

        $uuid = $answer['created'][0]['uuid'] ?? null;
        self::assertIsString($uuid);
        $mediaItem = self::mediaItems()->findOneByUuid($uuid);
        self::assertNotNull($mediaItem);
        self::assertMatchesRegularExpression(sprintf('#^%s/[0-9a-f]{16}/summer-sale\.png$#', preg_quote($uuid, '#')), $mediaItem->getPath());
        self::assertSame('/media/cms/' . $mediaItem->getPath(), $answer['created'][0]['url']);
        self::assertNull($answer['created'][0]['folder']);

        /** @var FilesystemOperator $storage */
        $storage = self::getContainer()->get('setono_sylius_cms.storage');
        self::assertTrue($storage->fileExists($mediaItem->getPath()));
    }

    /**
     * @test
     */
    public function it_refuses_a_file_of_a_type_the_library_does_not_take_naming_the_types_it_takes_and_the_limit(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        // Text named as a JPEG is text
        $answer = self::upload($client, $config['csrfToken'], [
            $this->uploadedFile('holiday.jpg', "Just some words, not a picture at all.\n"),
            $this->uploadedFile('logo.png', self::png()),
        ]);

        self::assertCount(1, $answer['created']);
        self::assertSame('logo', $answer['created'][0]['name']);
        self::assertSame([[
            'filename' => 'holiday.jpg',
            'error' => sprintf('holiday.jpg is a TXT file, which the media library doesn\'t take. It takes JPEG, PNG, GIF, WebP, AVIF, SVG, MP4, WebM, PDF, up to %s each.', self::formattedLimit($config['media']['maxUploadSize'])),
        ]], $answer['refused']);
    }

    /**
     * @test
     */
    public function it_refuses_a_file_that_php_refused_as_too_big(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $answer = self::upload($client, $config['csrfToken'], [$this->uploadedFile('poster.png', self::png(), \UPLOAD_ERR_INI_SIZE)]);

        self::assertSame([], $answer['created']);
        self::assertSame([['filename' => 'poster.png', 'error' => sprintf('poster.png is over the upload limit of %s.', self::formattedLimit($config['media']['maxUploadSize']))]], $answer['refused']);
    }

    /**
     * A file whose content matches an existing media item's current file offers that media item instead, and the editor
     * can still upload a copy
     *
     * @test
     */
    public function it_offers_the_media_item_a_file_duplicates_and_uploads_a_copy_when_asked_to(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $first = self::upload($client, $config['csrfToken'], [$this->uploadedFile('logo.png', self::png())]);
        $existing = $first['created'][0]['uuid'] ?? null;
        self::assertIsString($existing);

        $again = self::upload($client, $config['csrfToken'], [$this->uploadedFile('logo-final-FINAL.png', self::png())]);

        self::assertSame([], $again['created']);
        self::assertCount(1, $again['duplicates']);
        self::assertSame('logo-final-FINAL.png', $again['duplicates'][0]['filename']);
        self::assertSame($existing, $again['duplicates'][0]['mediaItem']['uuid']);
        self::assertSame('logo', $again['duplicates'][0]['mediaItem']['name']);

        $copy = self::upload($client, $config['csrfToken'], [$this->uploadedFile('logo-final-FINAL.png', self::png())], copy: true);

        self::assertSame([], $copy['duplicates']);
        self::assertCount(1, $copy['created']);
        self::assertNotSame($existing, $copy['created'][0]['uuid']);
        self::assertSame('logo-final-FINAL', $copy['created'][0]['name']);
    }

    /**
     * A JPEG uploaded with its location in its EXIF is stored without it, so the stored file of one uploaded again has
     * the same content
     *
     * @test
     */
    public function it_strips_an_images_metadata_before_storing_it(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $answer = self::upload($client, $config['csrfToken'], [$this->uploadedFile('home.jpg', (string) file_get_contents(dirname(__DIR__, 3) . '/Fixture/media/rotated-with-location.jpg'))]);

        $created = $answer['created'][0] ?? null;
        self::assertIsArray($created);
        // Turned upright
        self::assertSame([20, 40], [$created['width'], $created['height']]);
        self::assertIsString($created['url']);
        $stored = (string) file_get_contents(self::mediaDirectory() . substr($created['url'], strlen('/media/cms')));
        self::assertStringNotContainsString('Exif', $stored);
        self::assertStringNotContainsString('Acme Phone', $stored);
    }

    /**
     * @test
     */
    public function it_strips_an_avif_s_metadata_before_storing_it(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $avif = (string) file_get_contents(dirname(__DIR__, 3) . '/Fixture/media/rotated-with-location.avif');

        $answer = self::upload($client, $config['csrfToken'], [$this->uploadedFile('home.avif', $avif)]);

        $created = $answer['created'][0] ?? null;
        self::assertIsArray($created);
        self::assertSame('image/avif', $created['mimeType']);
        self::assertIsString($created['url']);
        $stored = (string) file_get_contents(self::mediaDirectory() . substr($created['url'], strlen('/media/cms')));
        self::assertSame(strlen($avif), strlen($stored));
        self::assertStringNotContainsString('Acme Phone', $stored);
        self::assertStringNotContainsString('GPSLatitude', $stored);
    }

    /**
     * @test
     */
    public function it_sanitises_an_svg(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $answer = self::upload($client, $config['csrfToken'], [$this->uploadedFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" onload="alert(1)"><script>alert(2)</script><rect width="120" height="40"/></svg>')]);

        $created = $answer['created'][0] ?? null;
        self::assertIsArray($created);
        self::assertSame('image/svg+xml', $created['mimeType']);
        self::assertSame([120, 40], [$created['width'], $created['height']]);
        self::assertIsString($created['url']);
        $stored = (string) file_get_contents(self::mediaDirectory() . substr($created['url'], strlen('/media/cms')));
        self::assertStringNotContainsString('alert', $stored);
        self::assertStringContainsString('<rect', $stored);
    }

    /**
     * @test
     */
    public function it_needs_the_csrf_token_files_and_a_folder_that_exists(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $client->request('POST', '/admin/cms/api/media', [], ['files' => [$this->uploadedFile('logo.png', self::png())]], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/admin/cms/api/media', [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $config['csrfToken']]);
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/admin/cms/api/media', ['folder' => '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f'], ['files' => [$this->uploadedFile('logo.png', self::png())]], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $config['csrfToken']]);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_gives_the_library_and_the_registry_the_effective_upload_limit_and_the_types_of_each_kind(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertIsArray($registry);
        self::assertSame($config['media'], $registry['media'] ?? null);
        self::assertGreaterThan(0, $config['media']['maxUploadSize']);
        self::assertSame([
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'],
            'video' => ['video/mp4', 'video/webm'],
            'document' => ['application/pdf'],
        ], $config['media']['types']);
    }

    private static function mediaItems(): MediaItemRepositoryInterface
    {
        /** @var MediaItemRepositoryInterface $repository */
        $repository = self::getContainer()->get(MediaItemRepositoryInterface::class);

        return $repository;
    }

    private static function formattedLimit(int $bytes): string
    {
        return FileSize::format($bytes);
    }
}
