<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocation;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocationTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * A content type's own fields are drafted, in the entry format, and published, like the base fields. Required ones are
 * enforced by validation. See "Content types" in docs/spec/extending.md
 */
final class ContentTypeFieldsTest extends BuilderApiTestCase
{
    use StoreLocations;

    /**
     * @test
     */
    public function it_drafts_a_store_locations_city_and_directions_and_publishes_them_onto_its_rows(): void
    {
        $client = self::createClient();
        $storeLocation = self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus', 'Turn left', published: true);
        $csrfToken = self::openBuilder($client, $storeLocation);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $storeLocation->getUuid(), ['lockToken' => self::entryLockToken($client, $storeLocation), 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'city', 'locale' => null, 'value' => 'Aarhus C'],
            ['op' => 'set_entry_value', 'field' => 'directions', 'locale' => 'en_US', 'value' => 'Turn right at the fountain'],
        ]], $csrfToken);

        self::assertSame(['status' => 'unpublished_changes', 'lockToken' => 1], $response);
        $draft = self::draftOfEntry($storeLocation)?->getContent();
        self::assertStringContainsString('"fields":{"city":"Aarhus C","noindex":false}', (string) $draft);
        self::assertStringContainsString('"fields":{"directions":"Turn right at the fountain","metaDescription":null,"metaTitle":null,"slug":"aarhus","title":"Aarhus"}', (string) $draft);
        self::assertSame('Aarhus', self::reloadStoreLocation($storeLocation)->getCity(), 'The rows keep the published city until the draft is published');

        $workingCopy = self::json($client, 'GET', '/admin/cms/api/entries/' . $storeLocation->getUuid());
        self::assertIsArray($workingCopy);
        self::assertSame(['city' => 'Aarhus C', 'noindex' => false], $workingCopy['entry']['fields'] ?? null);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $storeLocation->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        $published = self::reloadStoreLocation($storeLocation);
        self::assertSame('Aarhus C', $published->getCity());
        $translation = $published->getTranslation('en_US');
        self::assertInstanceOf(StoreLocationTranslation::class, $translation);
        self::assertSame('Turn right at the fountain', $translation->getDirections());
        self::assertNull(self::draftOfEntry($storeLocation));
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_a_store_location_without_its_required_city(): void
    {
        $client = self::createClient();
        $storeLocation = self::createStoreLocation('Aarhus', 'aarhus');
        $csrfToken = self::openBuilder($client, $storeLocation);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $storeLocation->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame(['block' => null, 'field' => 'city', 'locale' => null], array_intersect_key($response['errors'][0] ?? [], ['block' => true, 'field' => true, 'locale' => true]));
    }

    private static function entryLockToken(KernelBrowser $client, EntryInterface $entry): int
    {
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $entry->getUuid());
        self::assertIsArray($response);
        self::assertIsInt($response['lockToken'] ?? null);

        return $response['lockToken'];
    }

    private static function draftOfEntry(StoreLocation $storeLocation): ?DraftInterface
    {
        /** @var DraftRepositoryInterface $drafts */
        $drafts = self::getContainer()->get('setono_sylius_cms.repository.draft');

        return $drafts->findOneByEntry(self::reloadStoreLocation($storeLocation));
    }

    private static function reloadStoreLocation(StoreLocation $storeLocation): StoreLocation
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.store_location');
        $manager->clear();

        $reloaded = self::getContainer()->get('app.repository.store_location')->findOneBy(['uuid' => $storeLocation->getUuid()]);
        self::assertInstanceOf(StoreLocation::class, $reloaded);

        return $reloaded;
    }
}
