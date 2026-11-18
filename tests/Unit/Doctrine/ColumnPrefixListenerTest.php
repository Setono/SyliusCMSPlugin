<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Doctrine\ColumnPrefixListener;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\OutletLocation;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Component\Core\Model\Channel;

final class ColumnPrefixListenerTest extends TestCase
{
    use ProphecyTrait;

    private const CONTENT_TYPES = [
        'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
        'store_location' => ['model' => Location::class, 'translation' => null, 'resource' => 'app.store_location'],
    ];

    /**
     * @test
     */
    public function it_prefixes_the_columns_of_the_fields_a_content_types_model_declares_with_its_code(): void
    {
        $metadata = new ClassMetadata(Location::class);
        $metadata->mapField(['fieldName' => 'city', 'type' => 'string', 'nullable' => true]);
        $metadata->mapField(['fieldName' => 'openingHours', 'columnName' => 'opening_hours', 'type' => 'json', 'nullable' => true]);

        $this->load($metadata);

        self::assertSame('store_location_city', $metadata->getColumnName('city'));
        self::assertSame('store_location_opening_hours', $metadata->getColumnName('openingHours'));
        self::assertSame('city', $metadata->getFieldName('store_location_city'));
        self::assertSame('store_location_city', $metadata->getFieldMapping('city')['columnName']);
    }

    /**
     * Each subclass of Entry shares the entry table with every other, so only the columns of the root are shared
     *
     * @test
     */
    public function it_leaves_the_columns_of_the_fields_a_content_type_inherits_from_the_entry_alone(): void
    {
        $metadata = new ClassMetadata(Location::class);
        $metadata->addInheritedFieldMapping(['fieldName' => 'uuid', 'columnName' => 'uuid', 'type' => 'guid', 'inherited' => Entry::class, 'declared' => Entry::class]);

        $this->load($metadata);

        self::assertSame('uuid', $metadata->getColumnName('uuid'));
    }

    /**
     * A project's subclass of a shipped model, such as of Page, which is a mapped superclass until it's its resource's
     * model, gets the mapped superclass's fields as its own, and keeps the shipped content type's code
     *
     * @test
     */
    public function it_prefixes_the_columns_of_the_fields_of_the_mapped_superclasses_between_the_entry_and_the_model(): void
    {
        $metadata = new ClassMetadata(OutletLocation::class);
        $metadata->addInheritedFieldMapping(['fieldName' => 'city', 'columnName' => 'city', 'type' => 'string', 'declared' => Location::class]);
        $metadata->mapField(['fieldName' => 'outletCode', 'columnName' => 'outlet_code', 'type' => 'string', 'nullable' => true]);

        $this->load($metadata, ['store_location' => ['model' => OutletLocation::class, 'translation' => null, 'resource' => 'app.store_location']]);

        self::assertSame('store_location_city', $metadata->getColumnName('city'));
        self::assertSame('store_location_outlet_code', $metadata->getColumnName('outletCode'));
    }

    /**
     * @test
     */
    public function it_prefixes_the_columns_of_the_fields_a_content_types_translation_class_declares_with_its_code(): void
    {
        $metadata = new ClassMetadata(PageTranslation::class);
        $metadata->addInheritedFieldMapping(['fieldName' => 'title', 'columnName' => 'title', 'type' => 'string', 'inherited' => EntryTranslation::class, 'declared' => EntryTranslation::class]);
        $metadata->mapField(['fieldName' => 'subtitle', 'type' => 'string', 'nullable' => true]);

        $this->load($metadata);

        self::assertSame('page_subtitle', $metadata->getColumnName('subtitle'));
        self::assertSame('title', $metadata->getColumnName('title'));
    }

    /**
     * @test
     */
    public function it_prefixes_the_join_columns_of_the_associations_a_content_types_model_declares(): void
    {
        $metadata = new ClassMetadata(Location::class);
        $metadata->mapManyToOne(['fieldName' => 'channel', 'targetEntity' => Channel::class, 'joinColumns' => [['name' => 'channel_id', 'referencedColumnName' => 'id']]]);

        $this->load($metadata);

        $mapping = $metadata->getAssociationMapping('channel');
        self::assertSame('store_location_channel_id', $mapping['joinColumns'][0]['name'] ?? null);
        self::assertSame(['store_location_channel_id' => 'id'], $mapping['sourceToTargetKeyColumns'] ?? null);
        self::assertSame(['id' => 'store_location_channel_id'], $mapping['targetToSourceKeyColumns'] ?? null);
        self::assertSame(['store_location_channel_id' => 'store_location_channel_id'], $mapping['joinColumnFieldNames'] ?? null);
        self::assertSame('channel', $metadata->getFieldForColumn('store_location_channel_id'));
    }

    /**
     * @test
     */
    public function it_leaves_the_roots_a_translation_class_shared_by_content_types_and_other_classes_alone(): void
    {
        foreach ([Entry::class, EntryTranslation::class, Recipe::class] as $class) {
            $metadata = new ClassMetadata($class);
            $metadata->mapField(['fieldName' => 'note', 'type' => 'string', 'nullable' => true]);

            $this->load($metadata);

            self::assertSame('note', $metadata->getColumnName('note'), $class);
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes
     */
    private function load(ClassMetadata $metadata, array $contentTypes = self::CONTENT_TYPES): void
    {
        (new ColumnPrefixListener($contentTypes))->loadClassMetadata(
            new LoadClassMetadataEventArgs($metadata, $this->prophesize(EntityManagerInterface::class)->reveal()),
        );
    }
}
