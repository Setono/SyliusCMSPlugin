<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Rating;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Review;
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
     *
     * @dataProvider provideContentTypeClasses
     */
    public function it_reads_a_content_types_fields_of_any_visibility_translatable_where_they_sit(bool $translation): void
    {
        $fields = (new FieldReader())->readContentType(Teaser::class, $translation);

        self::assertSame(['heading', 'code', 'size', 'private'], array_keys($fields));
        foreach ($fields as $field) {
            self::assertSame($translation, $field->translatable);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideContentTypeClasses(): iterable
    {
        yield 'the entry class' => [false];
        yield 'the translation class' => [true];
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
    public function it_reads_the_field_type_a_project_declares_on_its_own_field_attribute(): void
    {
        $rating = (new FieldReader())->read(Review::class)['rating'];

        self::assertSame('app_rating', $rating->type->code);
        self::assertFalse($rating->type->translatable);
        self::assertSame([BindingType::Number], $rating->type->bindable);
        self::assertSame('app-rating', $rating->type->control);
        self::assertSame(Rating::class . 'Handler', $rating->handler(), 'The handler is the attribute class name plus "Handler"');
    }

    /**
     * @test
     */
    public function a_shipped_field_type_brings_no_field_control_of_its_own(): void
    {
        $quote = (new FieldReader())->read(Review::class)['quote'];

        self::assertSame('text', $quote->type->code);
        self::assertNull($quote->type->control);
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
