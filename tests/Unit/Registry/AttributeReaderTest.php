<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\SignedIn;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\OutletLocation;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

final class AttributeReaderTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_the_content_type_from_the_nearest_class_that_has_one(): void
    {
        $subclass = new class() extends Page {
        };

        self::assertSame('page', AttributeReader::contentType($subclass::class)?->code);
    }

    /**
     * @test
     */
    public function it_reads_every_content_type_option_from_the_nearest_class_that_has_the_attribute(): void
    {
        $contentType = AttributeReader::contentType(OutletLocation::class);

        self::assertNotNull($contentType);
        self::assertSame('location', $contentType->code);
        self::assertSame('app.cms.content_type.location', $contentType->label);
        self::assertSame(2, $contentType->version);
        self::assertSame('stores', $contentType->prefix);
        self::assertEquals([new Allow('*')], $contentType->blocks);
        self::assertEquals([new Card('grid', 'cms/card/location/grid.html.twig')], $contentType->cards);
        self::assertSame(['city' => 'ASC'], $contentType->order);
        self::assertSame(Recipe::class, $contentType->categoryType);
        self::assertSame('title', $contentType->descriptionField);
    }

    /**
     * @test
     */
    public function it_gives_a_content_type_without_options_their_defaults(): void
    {
        $contentType = AttributeReader::contentType(Recipe::class);

        self::assertNotNull($contentType);
        self::assertSame(1, $contentType->version);
        self::assertNull($contentType->prefix);
        self::assertNull($contentType->blocks);
        self::assertSame([], $contentType->cards);
        self::assertSame(['createdAt' => 'DESC'], $contentType->order);
        self::assertNull($contentType->categoryType);
        self::assertNull($contentType->descriptionField);
    }

    /**
     * @test
     */
    public function it_reads_no_attribute_from_a_class_that_has_none(): void
    {
        self::assertNull(AttributeReader::contentType(\stdClass::class));
        self::assertNull(AttributeReader::blockType(\stdClass::class));
        self::assertSame('plain', AttributeReader::blockType(Plain::class)?->code);
        self::assertNull(AttributeReader::layout(Plain::class));
        self::assertSame('landing', AttributeReader::layout(Landing::class)?->code);
        self::assertNull(AttributeReader::conditionKind(Plain::class));
        self::assertSame('signed_in', AttributeReader::conditionKind(SignedIn::class)?->code);
    }
}
