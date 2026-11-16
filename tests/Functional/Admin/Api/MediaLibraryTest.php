<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * What the library shows: All media, Unsorted or a folder, newest first, with search over the name, the original
 * filename and the alt text in every locale, and the kind and missing-alt filters. See "Folders" and "Search and
 * filters" in docs/spec/media.md
 */
final class MediaLibraryTest extends MediaApiTestCase
{
    /**
     * @test
     */
    public function it_shows_all_media_newest_first_unsorted_or_one_folder(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $campaigns = self::createMediaFolder('Campaigns');
        $summer = self::createMediaFolder('Summer', $campaigns);
        $logo = $this->uploadMediaItem('logo.png', self::png(10, 10));
        $banner = $this->uploadMediaItem('banner.jpg', self::jpeg(), $campaigns);
        $beach = $this->uploadMediaItem('beach.jpg', self::jpeg(20, 20), $summer);

        self::assertSame([$beach->getUuid(), $banner->getUuid(), $logo->getUuid()], self::uuids($client, ''));
        self::assertSame([$logo->getUuid()], self::uuids($client, 'folder=unsorted'));
        // Only what's in the folder itself, not in the folders inside it
        self::assertSame([$banner->getUuid()], self::uuids($client, 'folder=' . $campaigns->getUuid()));
        self::assertSame([$beach->getUuid()], self::uuids($client, 'folder=' . $summer->getUuid()));

        $client->request('GET', '/admin/cms/api/media?folder=0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_searches_the_name_the_original_filename_and_the_alt_text_in_every_locale(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $byName = $this->uploadMediaItem('IMG_0001.jpg', self::jpeg(10, 10));
        $byName->setName('Golden Retriever on the beach');
        $byFilename = $this->uploadMediaItem('golden-hour.png', self::png(11, 11));
        $byFilename->setName('Sunset');
        $byAltText = $this->uploadMediaItem('IMG_0003.jpg', self::jpeg(12, 12));
        $byAltText->setAltText('de_DE', 'Ein goldener Hund');
        $neither = $this->uploadMediaItem('IMG_0004.jpg', self::jpeg(13, 13));
        self::flush();

        self::assertSame([$byAltText->getUuid(), $byFilename->getUuid(), $byName->getUuid()], self::uuids($client, 'q=GOLD'));
        self::assertSame([$byFilename->getUuid()], self::uuids($client, 'q=golden-hour'));
        // A wildcard is searched for as it is
        self::assertSame([], self::uuids($client, 'q=' . rawurlencode('%')));
        self::assertNotContains($neither->getUuid(), self::uuids($client, 'q=gold'));
    }

    /**
     * @test
     */
    public function it_filters_by_kind(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $image = $this->uploadMediaItem('logo.png', self::png());
        $document = $this->uploadMediaItem('prices.pdf', "%PDF-1.4\n%%EOF\n");

        self::assertSame([$image->getUuid()], self::uuids($client, 'kind=image'));
        self::assertSame([$document->getUuid()], self::uuids($client, 'kind=document'));
        self::assertSame([], self::uuids($client, 'kind=video'));

        $client->request('GET', '/admin/cms/api/media?kind=audio');
        self::assertResponseStatusCodeSame(400);
    }

    /**
     * Images without alt text in one or more of the store's locales are flagged, and the filter shows only them
     *
     * @test
     */
    public function it_flags_and_filters_images_missing_alt_text_in_one_or_more_of_the_stores_locales(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'de_DE');
        self::openMediaLibrary($client);
        $complete = $this->uploadMediaItem('complete.png', self::png(10, 10));
        $complete->setAltText('en_US', 'A dog');
        $complete->setAltText('de_DE', 'Ein Hund');
        $partial = $this->uploadMediaItem('partial.png', self::png(11, 11));
        $partial->setAltText('en_US', 'A cat');
        $none = $this->uploadMediaItem('none.png', self::png(12, 12));
        // Alt text in a locale the store doesn't have counts for nothing
        $none->setAltText('fr_FR', 'Un oiseau');
        $this->uploadMediaItem('prices.pdf', "%PDF-1.4\n%%EOF\n");
        self::flush();

        self::assertSame([$none->getUuid(), $partial->getUuid()], self::uuids($client, 'missingAlt=1'));

        $answer = self::json($client, 'GET', '/admin/cms/api/media?kind=image');
        self::assertIsArray($answer);
        self::assertIsArray($answer['items']);
        self::assertSame([true, true, false], array_column($answer['items'], 'missingAlt'));
        self::assertSame(['de_DE' => 'Ein Hund', 'en_US' => 'A dog'], $answer['items'][2]['altTexts']);
    }

    /**
     * @test
     */
    public function it_pages_through_a_large_library(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        for ($i = 1; $i <= 61; ++$i) {
            $this->uploadMediaItem(sprintf('pixel-%d.png', $i), self::png($i, 1));
        }

        $first = self::json($client, 'GET', '/admin/cms/api/media');
        $second = self::json($client, 'GET', '/admin/cms/api/media?page=2');

        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertSame([1, 2, 61], [$first['page'], $first['pages'], $first['total']]);
        self::assertIsArray($first['items']);
        self::assertCount(60, $first['items']);
        self::assertIsArray($second['items']);
        self::assertCount(1, $second['items']);
        self::assertSame('pixel-1', $second['items'][0]['name']);
    }

    /**
     * @test
     */
    public function it_is_for_admin_users_only(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/api/media');
        self::assertResponseRedirects();

        $client->request('GET', '/admin/cms/media');
        self::assertResponseRedirects();
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

        return array_column($answer['items'], 'uuid');
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->flush();
    }
}
