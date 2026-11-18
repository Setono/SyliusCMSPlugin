<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The test application's own field type, opening hours, as a project adds one: a field attribute with #[FieldType], a
 * handler, and a field control the builder loads from setono_sylius_cms.builder.scripts. Its handler validates the
 * stored value on publish, its reference to a photo loads and is indexed like any other, and its hydrated value renders.
 * The test application's Store hours block type holds it. See "Adding a field type" in docs/spec/extending.md
 */
final class OpeningHoursTest extends MediaApiTestCase
{
    private const BLOCK = '0193a5be-0000-7000-8000-0000000000a1';

    /**
     * @test
     */
    public function it_gives_the_field_its_handlers_schema_and_its_field_control_in_the_registry(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertIsArray($registry);
        self::assertIsArray($registry['blockTypes']);
        $storeHours = array_column($registry['blockTypes'], null, 'code')['app_store_hours'] ?? null;
        self::assertIsArray($storeHours);
        self::assertSame('Store hours', $storeHours['label']);
        $time = ['type' => 'string', 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'];
        self::assertSame([[
            'name' => 'hours',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'days' => [
                        'type' => 'object',
                        'propertyNames' => ['enum' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']],
                        'additionalProperties' => [
                            'type' => 'object',
                            'properties' => ['opens' => $time, 'closes' => $time],
                            'required' => ['opens', 'closes'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'photo' => ['type' => 'string', 'format' => 'uuid'],
                ],
                'additionalProperties' => false,
                'x-cms-options' => [
                    'days' => [
                        'monday' => 'Monday',
                        'tuesday' => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday' => 'Thursday',
                        'friday' => 'Friday',
                        'saturday' => 'Saturday',
                        'sunday' => 'Sunday',
                    ],
                    'strings' => ['opens' => 'Opens', 'closes' => 'Closes', 'photo' => 'Photo'],
                ],
                'x-cms-type' => 'app_opening_hours',
                'x-cms-control' => 'app-opening-hours',
                'x-cms-label' => 'Opening hours',
                'x-cms-help' => 'Days without hours are closed.',
                'x-cms-required' => false,
                'x-cms-translatable' => false,
            ],
        ]], $storeHours['fields']);
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_opening_hours_the_field_types_handler_rejects(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Visit us', 'visit-us', [self::storeHours([
            'days' => ['monday' => ['opens' => '17:00', 'closes' => '09:00'], 'friday' => ['opens' => '9 am', 'closes' => '17:00']],
        ])]);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => '2 errors need fixing', 'errors' => [
            ['block' => self::BLOCK, 'field' => 'hours', 'locale' => null, 'message' => 'On Monday the store closes before it opens.'],
            ['block' => self::BLOCK, 'field' => 'hours', 'locale' => null, 'message' => 'Enter Friday\'s hours as HH:MM, such as 09:00.'],
        ]], $response);
        self::assertFalse(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function it_publishes_opening_hours_the_handler_accepts_and_renders_them_hydrated_with_their_photo(): void
    {
        $client = self::createClient();
        $photo = $this->uploadMediaItem('shopfront.png', self::png());
        $page = self::createPageWithBlocks('Visit us', 'visit-us', [self::storeHours([
            'days' => ['saturday' => ['opens' => '10:00', 'closes' => '14:00'], 'monday' => ['opens' => '09:00', 'closes' => '17:30']],
            'photo' => $photo->getUuid(),
        ])]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/en_US/visit-us');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Monday 09:00–17:30', 'Saturday 10:00–14:00'],
            $crawler->filter('.cms-tree > .app-store-hours li')->each(static fn (Crawler $day): string => $day->text()),
        );
        self::assertSame('shopfront', $crawler->filter('.cms-tree > .app-store-hours .app-store-hours__photo')->text());
    }

    /**
     * @test
     */
    public function it_indexes_the_photo_the_opening_hours_refer_to_as_a_use_of_the_media_item(): void
    {
        $client = self::createClient();
        self::openMediaLibrary($client);
        $photo = $this->uploadMediaItem('shopfront.png', self::png());
        $page = self::createPageWithBlocks('Visit us', 'visit-us', [self::storeHours(['photo' => $photo->getUuid()])], published: true);

        $answer = self::json($client, 'GET', sprintf('/admin/cms/api/media/%s/uses', $photo->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['uses' => [
            ['entry' => $page->getUuid(), 'title' => 'Visit us', 'contentType' => 'Page', 'url' => '/admin/cms/entry/' . $page->getUuid(), 'draft' => true, 'published' => true],
        ]], $answer);
    }

    /**
     * @param array<string, mixed> $hours
     */
    private static function storeHours(array $hours): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'app_store_hours', 1, fields: ['hours' => $hours]);
    }
}
