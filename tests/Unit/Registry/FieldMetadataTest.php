<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Field\Number;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Rating;

/**
 * How a field holds a binding: as its whole value, inside its values, or not at all. See "Fields" in
 * docs/spec/content-model.md
 */
final class FieldMetadataTest extends TestCase
{
    /**
     * @test
     */
    public function it_binds_a_text_textarea_number_or_project_field_whole(): void
    {
        foreach ([new Text(label: 'Text'), new Number(label: 'Number'), new Rating(label: 'Rating')] as $field) {
            $metadata = self::metadata($field);

            self::assertTrue($metadata->bindsWholeValue(), $field::class);
            self::assertFalse($metadata->bindsInside(), $field::class);
        }
    }

    /**
     * @test
     */
    public function it_binds_a_link_or_rich_text_inside_its_values(): void
    {
        foreach ([new Link(label: 'Link'), new RichText(label: 'Content')] as $field) {
            $metadata = self::metadata($field);

            self::assertFalse($metadata->bindsWholeValue(), $field::class);
            self::assertTrue($metadata->bindsInside(), $field::class);
        }
    }

    /**
     * @test
     */
    public function it_binds_a_field_whose_type_accepts_no_binding_type_neither_way(): void
    {
        $metadata = self::metadata(new Toggle(label: 'Toggle'));

        self::assertFalse($metadata->bindsWholeValue());
        self::assertFalse($metadata->bindsInside());
    }

    private static function metadata(Field $field): FieldMetadata
    {
        $attribute = (new \ReflectionClass($field))->getAttributes(FieldType::class)[0] ?? null;
        self::assertNotNull($attribute);

        return new FieldMetadata('field', $field, $attribute->newInstance(), false, null);
    }
}
