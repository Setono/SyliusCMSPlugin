<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The hub's Library has Media, the media library's screen, which mounts the library app with its config. See
 * "Navigation" and "Media library" in docs/spec/admin.md
 */
final class MediaLibraryPageTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function the_hubs_library_opens_the_media_library_with_the_config_its_app_needs(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::addLocales('WEB', 'de_DE');

        $crawler = $client->request('GET', '/admin/cms');
        $link = $crawler->filter('[data-hub-menu] a.item')->reduce(static fn (Crawler $item): bool => 'Media' === trim($item->text()));
        self::assertCount(1, $link);
        $crawler = $client->click($link->link());

        self::assertResponseIsSuccessful();
        self::assertRouteSame('setono_sylius_cms_admin_media_index');
        self::assertSelectorTextContains('h1', 'Media');
        self::assertSelectorExists('[data-hub-menu] a.item.active[href="/admin/cms/media"]');

        /** @var array<string, mixed> $config */
        $config = json_decode((string) $crawler->filter('#cms-media-library')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['csrfToken', 'endpoints', 'locales', 'media', 'strings'], array_keys($config));
        self::assertSame(['media' => '/admin/cms/api/media', 'folders' => '/admin/cms/api/media-folders'], $config['endpoints']);
        // The store's locales, which media items have alt text in, the default first
        self::assertSame([['code' => 'en_US', 'name' => 'English (United States)'], ['code' => 'de_DE', 'name' => 'German (Germany)']], $config['locales']);
        self::assertIsArray($config['strings']);
        self::assertSame('All media', $config['strings']['setono_sylius_cms.builder.media.all_media'] ?? null);
        self::assertSame([], array_filter(array_keys($config['strings']), static fn (string $key): bool => !str_starts_with($key, 'setono_sylius_cms.builder.media.')));

        // The library app is part of the builder app
        self::assertSelectorExists('script[type="module"][src$="/bundles/setonosyliuscmsplugin/builder/builder.js"]');
        self::assertSelectorExists('link[rel="stylesheet"][href$="/bundles/setonosyliuscmsplugin/builder/builder.css"]');
    }
}
