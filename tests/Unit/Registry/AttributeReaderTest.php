<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;

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
    public function it_reads_no_attribute_from_a_class_that_has_none(): void
    {
        self::assertNull(AttributeReader::contentType(\stdClass::class));
        self::assertNull(AttributeReader::blockType(\stdClass::class));
        self::assertSame('plain', AttributeReader::blockType(Plain::class)?->code);
    }
}
