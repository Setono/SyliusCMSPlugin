<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Registry\UnknownBindablePropertyException;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevel;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevelParams;

/**
 * The bindable properties by binding, as they're declared, with the fields of their parameters classes. See
 * "Bindable properties" in docs/spec/extending.md
 */
final class BindablePropertyRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_a_property_with_its_declaration_and_the_fields_of_its_parameters_class(): void
    {
        $stockLevel = new StockLevel();
        $metadata = Bindings::registry([new ProductBrand(), $stockLevel])->get('product.stock_level');

        self::assertSame('product', $metadata->contextKey);
        self::assertSame('stock_level', $metadata->code);
        self::assertSame('product.stock_level', $metadata->binding());
        self::assertSame('app.binding.stock_level', $metadata->label);
        self::assertSame(BindingType::Number, $metadata->type);
        self::assertSame(StockLevelParams::class, $metadata->params);
        self::assertSame(['includeReserved', 'warehouse'], array_keys($metadata->fields));
        self::assertInstanceOf(Toggle::class, $metadata->fields['includeReserved']->field);
        self::assertInstanceOf(Choice::class, $metadata->fields['warehouse']->field);
        self::assertSame($stockLevel, $metadata->property);
    }

    /**
     * @test
     */
    public function it_lists_the_properties_in_the_order_they_are_declared(): void
    {
        $registry = Bindings::registry([new StockLevel(), new ProductBrand()]);

        self::assertSame(['product.stock_level', 'product.brand'], array_map(static fn ($metadata): string => $metadata->binding(), $registry->all()));
        self::assertTrue($registry->has('product.brand'));
        self::assertFalse($registry->has('product.colour'));
    }

    /**
     * @test
     */
    public function it_needs_its_context_key_but_not_the_channel_or_the_customer(): void
    {
        self::assertSame(['product'], Bindings::registry([new ProductBrand()])->get('product.brand')->needs());
    }

    /**
     * @test
     */
    public function it_tells_whether_a_field_accepts_its_type(): void
    {
        $metadata = Bindings::registry([new ProductBrand()])->get('product.brand');

        self::assertTrue($metadata->fits([BindingType::Text, BindingType::Number]));
        self::assertFalse($metadata->fits([BindingType::Number]));
    }

    /**
     * @test
     */
    public function it_throws_for_a_property_it_does_not_have(): void
    {
        $this->expectException(UnknownBindablePropertyException::class);
        $this->expectExceptionMessage('There is no bindable property "product.colour"');

        Bindings::registry()->get('product.colour');
    }
}
