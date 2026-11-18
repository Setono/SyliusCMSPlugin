<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocation;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocationTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A project's own content types are subclasses of Entry, and of EntryTranslation for their translatable fields, in the
 * entries' tables, whose columns the plugin prefixes with the content type's code. See "Content types" in
 * docs/spec/extending.md
 */
final class ContentTypesTest extends KernelTestCase
{
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_prefixes_the_columns_of_the_test_applications_content_types_with_their_codes(): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');

        self::assertSame('store_location_city', $manager->getClassMetadata(StoreLocation::class)->getColumnName('city'));
        self::assertSame('store_location_directions', $manager->getClassMetadata(StoreLocationTranslation::class)->getColumnName('directions'));
        self::assertSame('announcement_subtitle', $manager->getClassMetadata(Announcement::class)->getColumnName('subtitle'));

        $schema = (new SchemaTool($manager))->getSchemaFromMetadata([$manager->getClassMetadata(Entry::class), $manager->getClassMetadata(EntryTranslation::class)]);
        $entryColumns = array_keys($schema->getTable('setono_sylius_cms__entry')->getColumns());
        $translationColumns = array_keys($schema->getTable('setono_sylius_cms__entry_translation')->getColumns());

        self::assertContains('store_location_city', $entryColumns);
        self::assertContains('announcement_subtitle', $entryColumns);
        self::assertContains('snippet_context', $entryColumns);
        self::assertNotContains('city', $entryColumns);
        self::assertContains('store_location_directions', $translationColumns);
        self::assertNotContains('directions', $translationColumns);
    }

    /**
     * @test
     */
    public function it_stores_a_store_location_and_its_translation_under_its_code(): void
    {
        $storeLocation = self::createStoreLocation('Copenhagen', 'copenhagen', 'Copenhagen', 'Take the metro to Kongens Nytorv');
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager->clear();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $row = $connection->fetchAssociative('SELECT id, content_type, store_location_city FROM setono_sylius_cms__entry WHERE uuid = ?', [$storeLocation->getUuid()]);
        self::assertIsArray($row);
        self::assertSame('store_location', $row['content_type']);
        self::assertSame('Copenhagen', $row['store_location_city']);

        $translation = $connection->fetchAssociative('SELECT content_type, store_location_directions FROM setono_sylius_cms__entry_translation WHERE translatable_id = ?', [$row['id']]);
        self::assertSame(['content_type' => 'store_location', 'store_location_directions' => 'Take the metro to Kongens Nytorv'], $translation);

        /** @var RepositoryInterface<StoreLocation> $repository */
        $repository = self::getContainer()->get('app.repository.store_location');
        $reloaded = $repository->findOneBy(['uuid' => $storeLocation->getUuid()]);
        self::assertInstanceOf(StoreLocation::class, $reloaded);
        self::assertSame('Copenhagen', $reloaded->getCity());
        self::assertInstanceOf(StoreLocationTranslation::class, $reloaded->getTranslation('en_US'));
    }

    /**
     * @test
     */
    public function it_stores_an_announcement_under_its_code_with_the_translations_of_entries_without_translatable_fields_of_their_own(): void
    {
        $announcement = self::createAnnouncement('Free shipping');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $row = $connection->fetchAssociative('SELECT id, content_type, announcement_subtitle FROM setono_sylius_cms__entry WHERE uuid = ?', [$announcement->getUuid()]);
        self::assertIsArray($row);
        self::assertSame('announcement', $row['content_type']);
        self::assertSame('About Free shipping', $row['announcement_subtitle']);
        self::assertSame('entry', $connection->fetchOne('SELECT content_type FROM setono_sylius_cms__entry_translation WHERE translatable_id = ?', [$row['id']]));
    }
}
