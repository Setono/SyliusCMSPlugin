<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Product;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\SameTaxonProductsSource;
use Setono\SyliusCMSPlugin\Source\Product\TaxonFilter;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\Taxon;

/**
 * "Same taxon": the other visible products in the host page's product's main taxon, by their position there, then by
 * their ID. See "Product sources" in docs/spec/sources.md
 */
final class SameTaxonProductsSourceTest extends TestCase
{
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_same_taxon_source_of_products_that_needs_the_product_without_parameters(): void
    {
        $attribute = AttributeReader::source(SameTaxonProductsSource::class);

        self::assertNotNull($attribute);
        self::assertSame('same_taxon_products', $attribute->code);
        self::assertSame('setono_sylius_cms.source.same_taxon_products', $attribute->label);
        self::assertSame(ProductInterface::class, $attribute->of);
        self::assertSame(['product'], $attribute->needs);
        self::assertNull($attribute->params);
    }

    /**
     * @test
     */
    public function it_pages_the_other_visible_products_in_the_products_main_taxon_by_their_position_there_then_by_id_in_sql(): void
    {
        $shirts = new Taxon();
        $product = new Product();
        $product->setMainTaxon($shirts);

        $adapter = $this->source()->resolve(new NoParams(), self::context($product));

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertSame(sprintf(
            'SELECT product FROM %s product INNER JOIN product.productTaxons cms_source_product_taxon WITH cms_source_product_taxon.taxon = :cms_source_taxon WHERE product.enabled = :cms_visible_enabled AND product <> :cms_source_host_product ORDER BY cms_source_product_taxon.position ASC, product.id ASC',
            Product::class,
        ), $this->dql());
        self::assertSame($shirts, $this->parameter('cms_source_taxon'));
        self::assertSame($product, $this->parameter('cms_source_host_product'));
    }

    /**
     * @test
     */
    public function it_lists_nothing_when_the_product_has_no_main_taxon(): void
    {
        $adapter = $this->source()->resolve(new NoParams(), self::context(new Product()));

        self::assertInstanceOf(ArrayAdapter::class, $adapter);
        self::assertSame(0, $adapter->getNbResults());
        self::assertSame([], $this->queries);
    }

    /**
     * @test
     */
    public function it_lists_nothing_without_a_product(): void
    {
        $adapter = $this->source()->resolve(new NoParams(), Rendering::context());

        self::assertInstanceOf(ArrayAdapter::class, $adapter);
        self::assertSame(0, $adapter->getNbResults());
    }

    private function source(): SameTaxonProductsSource
    {
        return new SameTaxonProductsSource($this->visibleProducts(), new TaxonFilter(ProductTaxon::class));
    }

    private static function context(ProductInterface $product): RenderContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return Rendering::context(channel: $channel, hostPageContext: ['product' => $product]);
    }
}
