<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Untyped;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\WithUntypedField;

final class FieldReaderTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_the_public_properties_with_a_field_attribute_in_declaration_order(): void
    {
        $fields = (new FieldReader())->read(Teaser::class);

        self::assertSame(['heading', 'code', 'size'], array_keys($fields));
        self::assertSame('heading', $fields['heading']->name);
        self::assertInstanceOf(Text::class, $fields['heading']->field);
        self::assertSame('app.heading', $fields['heading']->field->label);
        self::assertSame('text', $fields['heading']->type->code);
        self::assertInstanceOf(Choice::class, $fields['size']->field);
        self::assertSame('choice', $fields['size']->type->code);
    }

    /**
     * @test
     */
    public function it_resolves_translatability_from_the_field_or_else_the_field_type(): void
    {
        $fields = (new FieldReader())->read(Teaser::class);

        self::assertTrue($fields['heading']->translatable, 'Text is translatable by default');
        self::assertFalse($fields['code']->translatable, 'The field overrides the field type');
        self::assertTrue($fields['size']->translatable, 'The field overrides the field type');
    }

    /**
     * @test
     */
    public function it_reads_the_property_default(): void
    {
        $fields = (new FieldReader())->read(Teaser::class);

        self::assertNull($fields['heading']->default);
        self::assertSame('s', $fields['size']->default);
    }

    /**
     * @test
     */
    public function a_field_is_optional_unless_it_says_it_is_required(): void
    {
        self::assertFalse((new FieldReader())->read(Teaser::class)['heading']->field->required);
        self::assertFalse((new Untyped(label: 'app.untyped'))->required);
    }

    /**
     * @test
     */
    public function it_names_the_handler_service(): void
    {
        self::assertSame(\Setono\SyliusCMSPlugin\Field\TextHandler::class, (new FieldReader())->read(Teaser::class)['heading']->handler());
    }

    /**
     * @test
     */
    public function it_refuses_a_field_attribute_without_a_field_type(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[FieldType]');

        (new FieldReader())->read(WithUntypedField::class);
    }
}
