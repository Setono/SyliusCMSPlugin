<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * What the builder's media picker needs beside what the media library's screen does: the library's endpoints and the
 * store's locales in the builder's config, media items of the kinds a field takes, and the media items a field holds by
 * their UUIDs. See "Media library" in docs/spec/admin.md
 */
final class MediaPickerTest extends MediaApiTestCase
{
    /**
     * @test
     */
    public function it_gives_the_builder_the_media_librarys_endpoints_and_the_stores_locales(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $client->loginUser(self::admin(), 'admin');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        self::assertResponseIsSuccessful();
        /** @var array{media: array{endpoints: array{media: string, folders: string}, locales: list<array{code: string, name: string}>}} $config */
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['media' => '/admin/cms/api/media', 'folders' => '/admin/cms/api/media-folders'], $config['media']['endpoints']);
        self::assertSame([['code' => 'en_US', 'name' => 'English (United States)']], $config['media']['locales']);
    }

    /**
     * A field that takes images and documents lists both, and no videos
     *
     * @test
     */
    public function it_filters_by_several_kinds(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $image = $this->uploadMediaItem('logo.png', self::png());
        $document = $this->uploadMediaItem('prices.pdf', "%PDF-1.4\n%%EOF\n");

        self::assertSame([$document->getUuid(), $image->getUuid()], self::uuids($client, 'kind=image,document'));
        self::assertSame([$image->getUuid()], self::uuids($client, 'kind=image,video'));

        $client->request('GET', '/admin/cms/api/media?kind=image,audio');
        self::assertResponseStatusCodeSame(400);
    }

    /**
     * A field shows the media items it holds by their names, so the picker looks them up by UUID. One that was deleted
     * isn't there
     *
     * @test
     */
    public function it_finds_media_items_by_their_uuids(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $logo = $this->uploadMediaItem('logo.png', self::png());
        $this->uploadMediaItem('banner.png', self::png(rgb: [0, 0, 200]));
        $prices = $this->uploadMediaItem('prices.pdf', "%PDF-1.4\n%%EOF\n");

        $found = self::uuids($client, sprintf('uuids=%s,%s,%s', $logo->getUuid(), '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', $prices->getUuid()));

        self::assertEqualsCanonicalizing([$logo->getUuid(), $prices->getUuid()], $found);
    }

    /**
     * @test
     */
    public function it_refuses_a_uuid_that_is_not_one(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);

        $client->request('GET', '/admin/cms/api/media?uuids=logo.png');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return list<string>
     */
    private static function uuids(KernelBrowser $client, string $query): array
    {
        $answer = self::json($client, 'GET', '/admin/cms/api/media?' . $query);
        self::assertResponseIsSuccessful();
        self::assertIsArray($answer);
        self::assertIsArray($answer['items']);

        return array_map(static function (mixed $item): string {
            self::assertIsArray($item);
            self::assertIsString($item['uuid']);

            return $item['uuid'];
        }, array_values($answer['items']));
    }
}
