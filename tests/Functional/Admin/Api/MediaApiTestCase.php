<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The media library's endpoints, which its screen and the builder's media picker share
 */
abstract class MediaApiTestCase extends BuilderApiTestCase
{
    use MediaItems;

    /**
     * Signs the admin user "sylius" in and opens the media library, which is where its app gets its config from
     *
     * @return array{csrfToken: string, endpoints: array{media: string, folders: string}, locales: list<array{code: string, name: string}>, media: array{maxUploadSize: int, types: array<string, list<string>>}, strings: array<string, string>}
     */
    protected static function openMediaLibrary(KernelBrowser $client): array
    {
        $client->loginUser(self::admin(), 'admin');
        $crawler = $client->request('GET', '/admin/cms/media');
        self::assertResponseIsSuccessful();

        /** @var array{csrfToken: string, endpoints: array{media: string, folders: string}, locales: list<array{code: string, name: string}>, media: array{maxUploadSize: int, types: array<string, list<string>>}, strings: array<string, string>} $config */
        $config = json_decode((string) $crawler->filter('#cms-media-library')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);

        return $config;
    }

    /**
     * Uploads the files as the library does, as files[] in a form, into a folder or else Unsorted
     *
     * @param list<UploadedFile> $files
     *
     * @return array{created: list<array<string, mixed>>, duplicates: list<array{filename: string, mediaItem: array<string, mixed>}>, refused: list<array{filename: string, error: string}>}
     */
    protected static function upload(KernelBrowser $client, string $csrfToken, array $files, ?string $folder = null, bool $copy = false): array
    {
        $parameters = array_filter(['folder' => $folder, 'copy' => $copy ? '1' : null], static fn (?string $value): bool => null !== $value);
        $client->request('POST', '/admin/cms/api/media', $parameters, ['files' => $files], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();

        /** @var array{created: list<array<string, mixed>>, duplicates: list<array{filename: string, mediaItem: array<string, mixed>}>, refused: list<array{filename: string, error: string}>} $answer */
        $answer = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $answer;
    }
}
