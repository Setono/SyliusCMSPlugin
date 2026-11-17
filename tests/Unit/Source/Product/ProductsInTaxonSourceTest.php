<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Product;

use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\ProductsInTaxonParams;
use Setono\SyliusCMSPlugin\Source\Product\ProductsInTaxonSource;
use Setono\SyliusCMSPlugin\Source\Product\TaxonFilter;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\Taxon;

/**
 * "In taxon": the products in a taxon, or in it or below it, in the order editors pick, each order ending in the ID so
 * pages never overlap. See "Product sources" in docs/spec/sources.md
 */
final class ProductsInTaxonSourceTest extends TestCase
{
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_in_taxon_source_of_products(): void
    {
        $attribute = AttributeReader::source(ProductsInTaxonSource::class);

        self::assertNotNull($attribute);
        self::assertSame('products_in_taxon', $attribute->code);
        self::assertSame('setono_sylius_cms.source.products_in_taxon', $attribute->label);
        self::assertSame(ProductInterface::class, $attribute->of);
        self::assertSame([], $attribute->needs);
        self::assertSame(ProductsInTaxonParams::class, $attribute->params);
    }

    /**
     * @test
     */
    public function it_pages_the_visible_products_in_sql(): void
    {
        $adapter = $this->source()->resolve(self::params(), self::context());

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertStringStartsWith('SELECT product FROM ' . \Sylius\Component\Core\Model\Product::class . ' product', $this->dql());
        self::assertStringContainsString('product.enabled = :cms_visible_enabled', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_the_products_in_the_taxon_by_their_position_in_it_then_by_id(): void
    {
        $taxon = self::taxon();
        $this->source()->resolve(self::params($taxon), self::context());

        self::assertStringContainsString('INNER JOIN product.productTaxons cms_source_product_taxon WITH cms_source_product_taxon.taxon = :cms_source_taxon', $this->dql());
        self::assertSame($taxon, $this->parameter('cms_source_taxon'));
        self::assertStringEndsWith('ORDER BY cms_source_product_taxon.position ASC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_the_products_in_the_taxon_or_below_it_once_each_by_their_lowest_position_there_then_by_id(): void
    {
        $root = self::taxon(left: 1, right: 10);
        $taxon = self::taxon(left: 2, right: 7, root: $root);
        $this->source()->resolve(self::params($taxon, includeChildren: true), self::context());

        $subtree = 'cms_source_subtree_taxon.root = :cms_source_taxon_root AND cms_source_subtree_taxon.left >= :cms_source_taxon_left AND cms_source_subtree_taxon.right <= :cms_source_taxon_right';
        self::assertStringContainsString(sprintf('EXISTS (SELECT cms_source_in_subtree.id FROM %s cms_source_in_subtree INNER JOIN cms_source_in_subtree.taxon cms_source_subtree_taxon WHERE cms_source_in_subtree.product = product AND %s)', ProductTaxon::class, $subtree), $this->dql());
        self::assertStringContainsString(sprintf('(SELECT MIN(cms_source_position_in_subtree.position) FROM %s cms_source_position_in_subtree INNER JOIN cms_source_position_in_subtree.taxon cms_source_position_taxon WHERE cms_source_position_in_subtree.product = product AND cms_source_position_taxon.root = :cms_source_taxon_root AND cms_source_position_taxon.left >= :cms_source_taxon_left AND cms_source_position_taxon.right <= :cms_source_taxon_right) AS HIDDEN cms_source_position', ProductTaxon::class), $this->dql());
        self::assertStringNotContainsString('productTaxons', $this->dql());
        self::assertSame($root, $this->parameter('cms_source_taxon_root'));
        self::assertSame(2, $this->parameter('cms_source_taxon_left'));
        self::assertSame(7, $this->parameter('cms_source_taxon_right'));
        self::assertStringEndsWith('ORDER BY cms_source_position ASC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_takes_a_root_taxon_as_its_own_root(): void
    {
        $root = self::taxon(left: 1, right: 10);
        $this->source()->resolve(self::params($root, includeChildren: true), self::context());

        self::assertSame($root, $this->parameter('cms_source_taxon_root'));
    }

    /**
     * @test
     */
    public function it_lists_the_newest_products_first_then_by_id(): void
    {
        $this->source()->resolve(self::params(sort: ProductsInTaxonParams::SORT_NEWEST), self::context());

        self::assertStringEndsWith('ORDER BY product.createdAt DESC, product.id DESC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_the_products_by_the_lowest_price_of_their_enabled_variants_in_the_channel_then_by_id(): void
    {
        $this->source()->resolve(self::params(sort: ProductsInTaxonParams::SORT_PRICE), self::context());

        self::assertStringContainsString(sprintf(
            '(SELECT COALESCE(MIN(cms_source_pricing.price), 2147483647) FROM %s cms_source_pricing INNER JOIN cms_source_pricing.productVariant cms_source_variant WHERE cms_source_variant.product = product AND cms_source_variant.enabled = :cms_source_variant_enabled AND cms_source_pricing.channelCode = :cms_source_channel_code) AS HIDDEN cms_source_price',
            ChannelPricing::class,
        ), $this->dql());
        self::assertTrue($this->parameter('cms_source_variant_enabled'));
        self::assertSame('WEB', $this->parameter('cms_source_channel_code'));
        self::assertStringEndsWith('ORDER BY cms_source_price ASC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_the_products_by_their_name_in_the_locale_then_by_id(): void
    {
        $this->source()->resolve(self::params(sort: ProductsInTaxonParams::SORT_NAME), self::context('de_DE'));

        self::assertStringContainsString('INNER JOIN product.translations cms_source_translation WITH cms_source_translation.locale = :cms_source_locale', $this->dql());
        self::assertSame('de_DE', $this->parameter('cms_source_locale'));
        self::assertStringEndsWith('ORDER BY cms_source_translation.name ASC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_by_position_when_the_sort_is_not_one_it_knows(): void
    {
        $this->source()->resolve(self::params(sort: 'removed'), self::context());

        self::assertStringEndsWith('ORDER BY cms_source_product_taxon.position ASC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_without_a_taxon_and_asks_for_no_query(): void
    {
        $params = new ProductsInTaxonParams();

        $adapter = $this->source()->resolve($params, self::context());

        self::assertSame(0, $adapter->getNbResults());
        self::assertSame([], [...$adapter->getSlice(0, 8)]);
        self::assertSame([], $this->queries);
    }

    /**
     * @test
     */
    public function it_refuses_other_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->source()->resolve(new NoParams(), self::context());
    }

    private function source(): ProductsInTaxonSource
    {
        return new ProductsInTaxonSource($this->visibleProducts(), new TaxonFilter(ProductTaxon::class), ChannelPricing::class);
    }

    private static function params(?Taxon $taxon = null, bool $includeChildren = false, string $sort = ProductsInTaxonParams::SORT_POSITION): ProductsInTaxonParams
    {
        $params = new ProductsInTaxonParams();
        $params->taxon = $taxon ?? self::taxon();
        $params->includeChildren = $includeChildren;
        $params->sort = $sort;

        return $params;
    }

    private static function taxon(int $left = 1, int $right = 2, ?Taxon $root = null): Taxon
    {
        $taxon = new Taxon();
        $taxon->setLeft($left);
        $taxon->setRight($right);
        if (null !== $root) {
            $taxon->setParent($root);
            (new \ReflectionProperty(Taxon::class, 'root'))->setValue($taxon, $root);
        }

        return $taxon;
    }

    private static function context(string $localeCode = 'en_US'): RenderContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return Rendering::context(localeCode: $localeCode, channel: $channel);
    }
}
