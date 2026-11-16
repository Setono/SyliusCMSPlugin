<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

/**
 * Folders nest, and editors create, rename and move them. They exist only in the library, so no file moves.
 * See "Folders" in docs/spec/media.md
 */
final class MediaFoldersTest extends MediaApiTestCase
{
    /**
     * @test
     */
    public function it_creates_nested_folders_and_lists_them_by_name_with_their_parents(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $campaigns = self::json($client, 'POST', '/admin/cms/api/media-folders', ['name' => ' Campaigns ', 'parent' => null], $config['csrfToken']);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($campaigns);
        self::assertSame(['uuid', 'name', 'parent'], array_keys($campaigns));
        self::assertSame('Campaigns', $campaigns['name']);
        self::assertNull($campaigns['parent']);

        $summer = self::json($client, 'POST', '/admin/cms/api/media-folders', ['name' => 'Summer', 'parent' => $campaigns['uuid']], $config['csrfToken']);
        self::assertIsArray($summer);
        self::assertSame($campaigns['uuid'], $summer['parent']);
        self::json($client, 'POST', '/admin/cms/api/media-folders', ['name' => 'Brand'], $config['csrfToken']);

        $folders = self::json($client, 'GET', '/admin/cms/api/media-folders');
        self::assertIsArray($folders);
        self::assertIsArray($folders['folders']);
        self::assertSame(['Brand', 'Campaigns', 'Summer'], array_column($folders['folders'], 'name'));
        self::assertSame([null, null, $campaigns['uuid']], array_column($folders['folders'], 'parent'));
    }

    /**
     * @test
     */
    public function it_renames_a_folder_and_moves_it_without_moving_its_media_items_files(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $campaigns = self::createMediaFolder('Campaigns');
        $summer = self::createMediaFolder('Summer', $campaigns);
        $beach = $this->uploadMediaItem('beach.jpg', self::jpeg(), $summer);
        $path = $beach->getPath();

        $renamed = self::json($client, 'PATCH', '/admin/cms/api/media-folders/' . $summer->getUuid(), ['name' => 'Summer 2026'], $config['csrfToken']);
        self::assertResponseIsSuccessful();
        self::assertSame(['uuid' => $summer->getUuid(), 'name' => 'Summer 2026', 'parent' => $campaigns->getUuid()], $renamed);

        $moved = self::json($client, 'PATCH', '/admin/cms/api/media-folders/' . $summer->getUuid(), ['parent' => null], $config['csrfToken']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($moved);
        self::assertNull($moved['parent']);

        $media = self::json($client, 'GET', '/admin/cms/api/media?folder=' . $summer->getUuid());
        self::assertIsArray($media);
        self::assertIsArray($media['items']);
        self::assertSame([$beach->getUuid()], array_column($media['items'], 'uuid'));
        self::assertSame('/media/cms/' . $path, $media['items'][0]['url']);
        self::assertFileExists(self::mediaDirectory() . '/' . $path);
    }

    /**
     * @test
     */
    public function it_refuses_to_move_a_folder_into_itself_or_a_folder_inside_it_and_a_folder_without_a_name(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $campaigns = self::createMediaFolder('Campaigns');
        $summer = self::createMediaFolder('Summer', $campaigns);

        self::assertSame(['error' => 'The folder "Campaigns" can\'t go inside itself'], self::json($client, 'PATCH', '/admin/cms/api/media-folders/' . $campaigns->getUuid(), ['parent' => $summer->getUuid()], $config['csrfToken']));
        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'The folder "Campaigns" can\'t go inside itself'], self::json($client, 'PATCH', '/admin/cms/api/media-folders/' . $campaigns->getUuid(), ['parent' => $campaigns->getUuid()], $config['csrfToken']));
        self::assertSame(['error' => 'A folder needs a name of at most 255 characters'], self::json($client, 'POST', '/admin/cms/api/media-folders', ['name' => ''], $config['csrfToken']));
        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'There is no media folder 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f'], self::json($client, 'POST', '/admin/cms/api/media-folders', ['name' => 'Spring', 'parent' => '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f'], $config['csrfToken']));

        self::json($client, 'POST', '/admin/cms/api/media-folders', ['name' => 'Spring']);
        self::assertResponseStatusCodeSame(403);
        self::json($client, 'PATCH', '/admin/cms/api/media-folders/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', ['name' => 'Spring'], $config['csrfToken']);
        self::assertResponseStatusCodeSame(404);
    }
}
