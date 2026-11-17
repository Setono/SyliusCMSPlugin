<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductKind;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;

final class ProductKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_when_the_page_s_product_is_one_of_the_products(): void
    {
        $params = self::params(self::product('T_SHIRT'), self::product('JEANS'));

        self::assertTrue((new ProductKind())->matches($params, self::onProductPage(self::product('JEANS'))));
        self::assertFalse((new ProductKind())->matches($params, self::onProductPage(self::product('SHOES'))));
    }

    /**
     * A picked product that was deleted, or that the shopper may not see, isn't among the hydrated products, so it
     * matches nothing
     *
     * @test
     */
    public function it_matches_nothing_without_products(): void
    {
        self::assertFalse((new ProductKind())->matches(self::params(), self::onProductPage(self::product('T_SHIRT'))));
        self::assertFalse((new ProductKind())->matches(new ProductParams(), self::onProductPage(self::product('T_SHIRT'))));
    }

    /**
     * @test
     */
    public function it_matches_nothing_on_a_page_without_a_product(): void
    {
        $params = self::params(self::product('T_SHIRT'));

        self::assertFalse((new ProductKind())->matches($params, Rendering::context()));
        self::assertFalse((new ProductKind())->matches($params, Rendering::context(hostPageContext: ['product' => new Taxon()])), 'Not a product');
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The product condition takes %s, got %s', ProductParams::class, NoParams::class));

        (new ProductKind())->matches(new NoParams(), self::onProductPage(self::product('T_SHIRT')));
    }

    private static function params(ProductInterface ...$products): ProductParams
    {
        $params = new ProductParams();
        $params->products = ItemList::of(array_values($products));

        return $params;
    }

    private static function product(string $code): Product
    {
        $product = new Product();
        $product->setCode($code);

        return $product;
    }

    private static function onProductPage(Product $product): RenderContext
    {
        return Rendering::context(hostPageContext: ['product' => $product]);
    }
}
