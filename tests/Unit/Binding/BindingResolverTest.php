<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevel;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevelParams;
use Sylius\Component\Core\Model\Product;

/**
 * A binding resolves against the host-page context, the channel or the customer, and is empty when the property, its
 * object or its value is missing. See "Bindings" in docs/spec/slots.md
 */
final class BindingResolverTest extends TestCase
{
    /**
     * @test
     */
    public function it_resolves_the_property_of_the_host_page_s_object(): void
    {
        $product = new Product();
        $brand = new ProductBrand();

        $value = Bindings::resolver([$brand])->resolve(new StoredBinding('product.brand'), [BindingType::Text], Rendering::hydrationContext(Rendering::context(hostPageContext: ['product' => $product])));

        self::assertSame('Acme', $value);
        self::assertSame([$product], $brand->resolvedFor);
    }

    /**
     * @test
     */
    public function it_hydrates_the_property_s_parameters(): void
    {
        $stockLevel = new StockLevel();
        $context = Rendering::hydrationContext(Rendering::context(hostPageContext: ['product' => new Product()]));

        self::assertSame(12, Bindings::resolver([$stockLevel])->resolve(new StoredBinding('product.stock_level', ['includeReserved' => true]), [BindingType::Number], $context));
        self::assertInstanceOf(StockLevelParams::class, $stockLevel->resolvedWith[0]);
        self::assertTrue($stockLevel->resolvedWith[0]->includeReserved);
    }

    /**
     * @test
     */
    public function it_gives_a_number_as_its_string_to_a_field_that_accepts_text(): void
    {
        $context = Rendering::hydrationContext(Rendering::context(hostPageContext: ['product' => new Product()]));

        self::assertSame('7', Bindings::resolver([new StockLevel()])->resolve(new StoredBinding('product.stock_level'), [BindingType::Text, BindingType::Number], $context));
    }

    /**
     * @test
     */
    public function it_is_empty_where_the_page_does_not_have_the_context_key_s_object(): void
    {
        self::assertNull(Bindings::resolver([new ProductBrand()])->resolve(new StoredBinding('product.brand'), [BindingType::Text], Rendering::hydrationContext()));
    }

    /**
     * @test
     */
    public function it_is_empty_for_a_property_the_code_no_longer_has(): void
    {
        $context = Rendering::hydrationContext(Rendering::context(hostPageContext: ['product' => new Product()]));

        self::assertNull(Bindings::resolver([new ProductBrand()])->resolve(new StoredBinding('product.colour'), [BindingType::Text], $context));
    }

    /**
     * @test
     */
    public function it_is_empty_for_a_property_of_a_type_the_field_does_not_accept(): void
    {
        $context = Rendering::hydrationContext(Rendering::context(hostPageContext: ['product' => new Product()]));

        self::assertNull(Bindings::resolver([new ProductBrand()])->resolve(new StoredBinding('product.brand'), [BindingType::Number], $context));
    }

    /**
     * @test
     */
    public function it_is_empty_when_the_property_gives_an_empty_string(): void
    {
        $context = Rendering::hydrationContext(Rendering::context(hostPageContext: ['product' => new Product()]));

        self::assertNull(Bindings::resolver([new ProductBrand('')])->resolve(new StoredBinding('product.brand'), [BindingType::Text], $context));
    }
}
