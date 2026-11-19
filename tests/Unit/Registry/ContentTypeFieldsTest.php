<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\FlagshipShop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopProxy;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;

final class ContentTypeFieldsTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_the_shared_fields_from_the_model_and_the_translatable_ones_from_its_translations(): void
    {
        $fields = new ContentTypeFields(new FieldReader());

        $shared = $fields->shared(self::shop());
        $translatable = $fields->translatable(self::shop());

        self::assertSame(['city', 'openedOn', 'renovatedAt', 'parking', 'photo'], array_keys($shared));
        self::assertFalse($shared['city']->translatable, 'A Text field on the model is shared, whatever its field type\'s default');
        self::assertSame(['directions'], array_keys($translatable));
        self::assertTrue($translatable['directions']->translatable);
    }

    /**
     * @test
     */
    public function it_reads_the_private_fields_of_the_model_a_subclass_extends_before_its_own(): void
    {
        $fields = new ContentTypeFields(new FieldReader());

        self::assertSame(['city', 'openedOn', 'renovatedAt', 'parking', 'photo', 'manager'], array_keys($fields->shared(self::shop(FlagshipShop::class))));
    }

    /**
     * @test
     */
    public function it_has_no_fields_of_a_content_type_without_any(): void
    {
        $fields = new ContentTypeFields(new FieldReader());
        $recipe = new ContentTypeMetadata('recipe', 'app.recipe', 1, null, null, Recipe::class, EntryTranslation::class, 'app.recipe');

        self::assertSame([], $fields->shared($recipe));
        self::assertSame([], $fields->translatable($recipe));
        self::assertSame([], $fields->translatable(new ContentTypeMetadata('recipe', 'app.recipe', 1, null, null, Recipe::class, null, 'app.recipe')));
    }

    /**
     * @test
     */
    public function it_reads_a_fields_value_as_the_entry_format_stores_it(): void
    {
        $fields = new ContentTypeFields(new FieldReader());
        $shop = new Shop();
        $shop->setCity('Aarhus');
        $shop->setOpenedOn(new \DateTimeImmutable('2026-11-13 00:00:00', new \DateTimeZone('Europe/Copenhagen')));
        // As Doctrine hydrates a column that holds UTC, in PHP's default time zone
        $shop->setRenovatedAt(new \DateTimeImmutable('2026-11-13 09:30:00', new \DateTimeZone('America/New_York')));
        $translation = new ShopTranslation();
        $translation->setDirections("Turn left\nat the fountain");

        self::assertSame('Aarhus', $fields->read($shop, 'city'));
        self::assertSame('2026-11-13', $fields->read($shop, 'openedOn'));
        self::assertSame('2026-11-13T09:30:00Z', $fields->read($shop, 'renovatedAt'));
        self::assertFalse($fields->read($shop, 'parking'));
        self::assertNull($fields->read($shop, 'photo'));
        self::assertSame("Turn left\nat the fountain", $fields->read($translation, 'directions'));
    }

    /**
     * An entry Doctrine hasn't loaded yet, such as one a reference points to, holds the row's values once it's loaded
     *
     * @test
     */
    public function it_loads_a_doctrine_proxy_before_reading_its_field(): void
    {
        $proxy = new ShopProxy();

        self::assertSame('Aarhus', (new ContentTypeFields(new FieldReader()))->read($proxy, 'city'));
        self::assertTrue($proxy->__isInitialized());
    }

    /**
     * @test
     */
    public function it_writes_a_value_the_entry_format_stores_as_doctrine_maps_it(): void
    {
        $fields = new ContentTypeFields(new FieldReader());
        $shop = new FlagshipShop();

        $fields->write($shop, 'city', 'Odense');
        $fields->write($shop, 'openedOn', '2026-11-13');
        $fields->write($shop, 'renovatedAt', '2026-11-13T09:30:00Z');
        $fields->write($shop, 'parking', true);

        self::assertSame('Odense', $shop->getCity());
        self::assertEquals(new \DateTimeImmutable('2026-11-13T00:00:00Z'), $shop->getOpenedOn());
        self::assertEquals(new \DateTimeImmutable('2026-11-13T09:30:00Z'), $shop->getRenovatedAt());
        self::assertSame('UTC', $shop->getRenovatedAt()?->getTimezone()->getName());
        self::assertTrue($shop->hasParking());
    }

    /**
     * A stored value that doesn't fit counts as missing ("Values that don't fit" in docs/spec/content-model.md)
     *
     * @test
     */
    public function it_writes_a_missing_value_or_one_that_does_not_fit_the_property_as_its_default(): void
    {
        $fields = new ContentTypeFields(new FieldReader());
        $shop = new Shop();
        $shop->setCity('Odense');
        $shop->setOpenedOn(new \DateTimeImmutable('2026-11-13'));
        $shop->setParking(true);

        $fields->write($shop, 'city', ['not', 'a', 'string']);
        $fields->write($shop, 'openedOn', 'not a date');
        $fields->write($shop, 'parking', null);

        self::assertNull($shop->getCity());
        self::assertNull($shop->getOpenedOn());
        self::assertFalse($shop->hasParking());
    }

    /**
     * @test
     */
    public function it_refuses_to_read_or_write_a_property_that_is_no_field(): void
    {
        $fields = new ContentTypeFields(new FieldReader());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"uuid" is not one of the fields of');

        $fields->read(new Shop(), 'uuid');
    }

    /**
     * @param class-string<Shop> $model
     */
    private static function shop(string $model = Shop::class): ContentTypeMetadata
    {
        return new ContentTypeMetadata('shop', 'app.shop', 1, 'shops', null, $model, ShopTranslation::class, 'app.shop');
    }
}
