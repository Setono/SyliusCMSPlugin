<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonKind;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductInTaxonKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_when_the_page_s_product_is_in_one_of_the_taxons(): void
    {
        $shoes = self::taxon('SHOES');
        $params = self::params([self::taxon('JEANS'), $shoes]);

        self::assertTrue((new ProductInTaxonKind())->matches($params, self::onProductPage(self::product($shoes))));
        self::assertFalse((new ProductInTaxonKind())->matches($params, self::onProductPage(self::product(self::taxon('T_SHIRTS')))));
        self::assertFalse((new ProductInTaxonKind())->matches($params, self::onProductPage(self::product())), 'A product in no taxon');
    }

    /**
     * @test
     */
    public function it_matches_through_any_of_the_product_s_taxons_not_only_its_main_one(): void
    {
        $shoes = self::taxon('SHOES');
        $product = self::product(self::taxon('NEW'), $shoes);
        $product->setMainTaxon(self::taxon('NEW'));

        self::assertTrue((new ProductInTaxonKind())->matches(self::params([$shoes]), self::onProductPage($product)));
    }

    /**
     * Sylius doesn't require the main taxon to be one of the product's taxons
     *
     * @test
     */
    public function it_matches_through_the_product_s_main_taxon_when_it_is_not_one_of_its_taxons(): void
    {
        $shoes = self::taxon('SHOES');
        $sneakers = self::taxon('SNEAKERS', parent: $shoes);
        $product = self::product(self::taxon('NEW'));
        $product->setMainTaxon($sneakers);

        self::assertTrue((new ProductInTaxonKind())->matches(self::params([$sneakers]), self::onProductPage($product)));
        self::assertFalse((new ProductInTaxonKind())->matches(self::params([$shoes]), self::onProductPage($product)), 'A parent, without children');
        self::assertTrue((new ProductInTaxonKind())->matches(self::params([$shoes], includeChildren: true), self::onProductPage($product)), 'A parent, with children');
    }

    /**
     * @test
     */
    public function it_matches_a_product_in_a_child_taxon_only_when_it_includes_children(): void
    {
        $shoes = self::taxon('SHOES');
        $sneakers = self::taxon('SNEAKERS', parent: $shoes);
        $running = self::taxon('RUNNING', parent: $sneakers);

        self::assertFalse((new ProductInTaxonKind())->matches(self::params([$shoes]), self::onProductPage(self::product($sneakers))));
        self::assertTrue((new ProductInTaxonKind())->matches(self::params([$shoes], includeChildren: true), self::onProductPage(self::product($sneakers))));
        self::assertTrue((new ProductInTaxonKind())->matches(self::params([$shoes], includeChildren: true), self::onProductPage(self::product($running))), 'A grandchild');
        self::assertTrue((new ProductInTaxonKind())->matches(self::params([$shoes], includeChildren: true), self::onProductPage(self::product($shoes))), 'The taxon itself');
        self::assertFalse((new ProductInTaxonKind())->matches(self::params([$sneakers], includeChildren: true), self::onProductPage(self::product($shoes))), 'A parent');
    }

    /**
     * A picked taxon that was deleted, or that the shopper may not see, isn't among the hydrated taxons, so it matches
     * nothing
     *
     * @test
     */
    public function it_matches_nothing_without_taxons(): void
    {
        $product = self::product(self::taxon('SHOES'));

        self::assertFalse((new ProductInTaxonKind())->matches(self::params([]), self::onProductPage($product)));
        self::assertFalse((new ProductInTaxonKind())->matches(new ProductInTaxonParams(), self::onProductPage($product)));
    }

    /**
     * @test
     */
    public function it_matches_nothing_on_a_page_without_a_product(): void
    {
        $shoes = self::taxon('SHOES');

        self::assertFalse((new ProductInTaxonKind())->matches(self::params([$shoes]), Rendering::context()));
        self::assertFalse((new ProductInTaxonKind())->matches(self::params([$shoes]), Rendering::context(hostPageContext: ['product' => $shoes])), 'Not a product');
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The product in taxon condition takes %s, got %s', ProductInTaxonParams::class, NoParams::class));

        (new ProductInTaxonKind())->matches(new NoParams(), self::onProductPage(self::product()));
    }

    /**
     * @param list<TaxonInterface> $taxons
     */
    private static function params(array $taxons, bool $includeChildren = false): ProductInTaxonParams
    {
        $params = new ProductInTaxonParams();
        $params->taxons = ItemList::of($taxons);
        $params->includeChildren = $includeChildren;

        return $params;
    }

    private static function taxon(string $code, ?Taxon $parent = null): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCode($code);
        $parent?->addChild($taxon);

        return $taxon;
    }

    private static function product(Taxon ...$taxons): Product
    {
        $product = new Product();
        $product->setCode('PRODUCT');
        foreach ($taxons as $taxon) {
            $productTaxon = new ProductTaxon();
            $productTaxon->setTaxon($taxon);
            $product->addProductTaxon($productTaxon);
        }

        return $product;
    }

    private static function onProductPage(Product $product): RenderContext
    {
        return Rendering::context(hostPageContext: ['product' => $product]);
    }
}
