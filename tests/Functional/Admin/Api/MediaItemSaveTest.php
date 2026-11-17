<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\MediaItemSaved;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Saving a media item's metadata or moving its focal point takes effect at once, bumps changedAt and dispatches
 * MediaItemSaved after the transaction commits. See "Metadata" and "Changes take effect on save" in docs/spec/media.md
 * and ADR 0013
 */
final class MediaItemSaveTest extends MediaApiTestCase
{
    /**
     * @test
     */
    public function it_saves_the_name_credit_alt_text_and_focal_point_and_moves_it_into_a_folder(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        self::addLocales('WEB', 'de_DE');
        $campaigns = self::createMediaFolder('Campaigns');
        $mediaItem = $this->uploadMediaItem('IMG_0042.jpg', self::jpeg());
        $uuid = $mediaItem->getUuid();
        $changedAt = $mediaItem->getChangedAt();
        sleep(1);

        $answer = self::json($client, 'PATCH', '/admin/cms/api/media/' . $uuid, [
            'name' => '  Golden retriever  ',
            'credit' => 'Photo: Jane Doe',
            'altTexts' => ['en_US' => 'A golden retriever on the beach', 'de_DE' => 'Ein Golden Retriever am Strand'],
            'focalPoint' => ['x' => 0.25, 'y' => 0.8],
            'folder' => $campaigns->getUuid(),
        ], $config['csrfToken']);

        self::assertResponseIsSuccessful();
        self::assertIsArray($answer);
        self::assertSame('Golden retriever', $answer['name']);
        self::assertSame('Photo: Jane Doe', $answer['credit']);
        self::assertSame(['de_DE' => 'Ein Golden Retriever am Strand', 'en_US' => 'A golden retriever on the beach'], $answer['altTexts']);
        self::assertFalse($answer['missingAlt']);
        self::assertSame(['x' => 0.25, 'y' => 0.8], $answer['focalPoint']);
        self::assertSame($campaigns->getUuid(), $answer['folder']);
        self::assertNotSame($changedAt->format(\DATE_ATOM), $answer['changedAt']);

        // Stored
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->clear();
        $saved = self::mediaItems()->findOneByUuid($uuid);
        self::assertNotNull($saved);
        self::assertSame('Golden retriever', $saved->getName());
        self::assertSame('Ein Golden Retriever am Strand', $saved->getAltText('de_DE'));
        self::assertSame(0.25, $saved->getFocalPoint()->x);
        self::assertSame('Campaigns', $saved->getFolder()?->getName());
        self::assertGreaterThan($changedAt, $saved->getChangedAt());

        // An empty alt text removes it, and null moves it to Unsorted
        $answer = self::json($client, 'PATCH', '/admin/cms/api/media/' . $uuid, ['altTexts' => ['de_DE' => ''], 'folder' => null], $config['csrfToken']);
        self::assertIsArray($answer);
        self::assertSame(['en_US' => 'A golden retriever on the beach'], $answer['altTexts']);
        self::assertTrue($answer['missingAlt']);
        self::assertNull($answer['folder']);
    }

    /**
     * @test
     */
    public function it_dispatches_media_item_saved_after_the_transaction_commits(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $client->disableReboot();
        $mediaItem = $this->uploadMediaItem('logo.png', self::png());
        $uuid = $mediaItem->getUuid();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var list<array{MediaItemSaved, int, mixed}> $dispatched the event, the transaction nesting level, and the stored focal point */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(MediaItemSaved::class, static function (MediaItemSaved $event) use (&$dispatched, $connection): void {
            $dispatched[] = [$event, $connection->getTransactionNestingLevel(), $connection->fetchOne('SELECT focal_point_x FROM setono_sylius_cms__media_item WHERE uuid = ?', [$event->uuid])];
        });

        self::json($client, 'PATCH', '/admin/cms/api/media/' . $uuid, ['focalPoint' => ['x' => 0.1, 'y' => 0.9]], $config['csrfToken']);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $dispatched);
        [[$event, $level, $focalPointX]] = $dispatched;
        self::assertSame($uuid, $event->uuid);
        self::assertSame([$uuid], $event->getAffectedUuids());
        self::assertSame($outside, $level);
        self::assertEquals(0.1, $focalPointX);

        // Saving what it already has changes nothing
        self::json($client, 'PATCH', '/admin/cms/api/media/' . $uuid, ['focalPoint' => ['x' => 0.1, 'y' => 0.9]], $config['csrfToken']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $dispatched);
    }

    /**
     * @test
     *
     * @dataProvider invalidChanges
     *
     * @param array<string, mixed> $change
     */
    public function it_refuses_a_change_that_does_not_fit(string $file, array $change, string $error): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem($file, str_ends_with($file, '.pdf') ? "%PDF-1.4\n%%EOF\n" : self::png());

        $answer = self::json($client, 'PATCH', '/admin/cms/api/media/' . $mediaItem->getUuid(), $change, $config['csrfToken']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => $error], $answer);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidChanges(): iterable
    {
        yield 'an empty name' => ['logo.png', ['name' => ' '], 'A media item needs a name'];
        yield 'a focal point outside the image' => ['logo.png', ['focalPoint' => ['x' => 1.5, 'y' => 0.5]], 'A focal point\'s x and y must be from 0 to 1, not 1.5 and 0.5'];
        yield 'a focal point on a document' => ['prices.pdf', ['focalPoint' => ['x' => 0.2, 'y' => 0.2]], 'Only an image has alt text and a focal point'];
        yield 'alt text on a document' => ['prices.pdf', ['altTexts' => ['en_US' => 'Prices']], 'Only an image has alt text and a focal point'];
        yield 'alt text in a locale the store does not have' => ['logo.png', ['altTexts' => ['xx_XX' => 'Logo']], 'The store has no locale xx_XX'];
        yield 'a folder that does not exist' => ['logo.png', ['name' => 'Logo', 'folder' => '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f'], 'There is no media folder 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f'];
        yield 'a name that is not a string' => ['logo.png', ['name' => 42], 'The name must be a string'];
    }

    /**
     * @test
     */
    public function it_gives_only_a_video_a_poster(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $logo = $this->uploadMediaItem('logo.png', self::png());
        $photo = $this->uploadMediaItem('photo.jpg', self::jpeg());

        $answer = self::json($client, 'PATCH', '/admin/cms/api/media/' . $logo->getUuid(), ['poster' => $photo->getUuid()], $config['csrfToken']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Only a video has a poster, and its poster is an image'], $answer);
    }

    /**
     * @test
     */
    public function it_needs_the_csrf_token_and_a_media_item_that_exists(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);
        $mediaItem = $this->uploadMediaItem('logo.png', self::png());

        self::json($client, 'PATCH', '/admin/cms/api/media/' . $mediaItem->getUuid(), ['name' => 'Logo']);
        self::assertResponseStatusCodeSame(403);

        self::json($client, 'PATCH', '/admin/cms/api/media/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', ['name' => 'Logo'], $config['csrfToken']);
        self::assertResponseStatusCodeSame(404);
    }

    private static function mediaItems(): MediaItemRepositoryInterface
    {
        /** @var MediaItemRepositoryInterface $repository */
        $repository = self::getContainer()->get(MediaItemRepositoryInterface::class);

        return $repository;
    }
}
