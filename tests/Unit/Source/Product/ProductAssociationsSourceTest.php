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
use Setono\SyliusCMSPlugin\Source\Product\ProductAssociationsParams;
use Setono\SyliusCMSPlugin\Source\Product\ProductAssociationsSource;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Product\Model\ProductAssociation;

/**
 * "Associations": the visible products the host page's product is associated with, of one association type or of
 * every type, by their ID. See "Product sources" in docs/spec/sources.md
 */
final class ProductAssociationsSourceTest extends TestCase
{
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_associations_source_of_products_that_needs_the_product(): void
    {
        $attribute = AttributeReader::source(ProductAssociationsSource::class);

        self::assertNotNull($attribute);
        self::assertSame('product_associations', $attribute->code);
        self::assertSame('setono_sylius_cms.source.product_associations', $attribute->label);
        self::assertSame(ProductInterface::class, $attribute->of);
        self::assertSame(['product'], $attribute->needs);
        self::assertSame(ProductAssociationsParams::class, $attribute->params);
    }

    /**
     * Each product once, however many of the product's associations it's in, so the paginator pages the query as it is
     *
     * @test
     */
    public function it_pages_the_visible_products_associated_with_the_product_of_every_type_by_their_id_in_sql(): void
    {
        $product = new Product();

        $adapter = $this->source()->resolve(new ProductAssociationsParams(), self::context($product));

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertSame(sprintf(
            'SELECT product FROM %s product WHERE product.enabled = :cms_visible_enabled AND (EXISTS (SELECT cms_source_association.id FROM %s cms_source_association INNER JOIN cms_source_association.associatedProducts cms_source_associated WHERE cms_source_association.owner = :cms_source_owner AND cms_source_associated.id = product.id)) ORDER BY product.id ASC',
            Product::class,
            ProductAssociation::class,
        ), $this->dql());
        self::assertSame($product, $this->parameter('cms_source_owner'));
    }

    /**
     * @test
     */
    public function it_keeps_the_products_of_the_association_type(): void
    {
        $params = new ProductAssociationsParams();
        $params->associationType = 'ACCESSORIES';

        $this->source()->resolve($params, self::context(new Product()));

        self::assertStringContainsString('INNER JOIN cms_source_association.type cms_source_association_type WHERE cms_source_association.owner = :cms_source_owner AND cms_source_associated.id = product.id AND cms_source_association_type.code = :cms_source_association_type)', $this->dql());
        self::assertSame('ACCESSORIES', $this->parameter('cms_source_association_type'));
        self::assertStringEndsWith('ORDER BY product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_without_a_product(): void
    {
        $adapter = $this->source()->resolve(new ProductAssociationsParams(), Rendering::context(hostPageContext: ['product' => new Taxon()]));

        self::assertInstanceOf(ArrayAdapter::class, $adapter);
        self::assertSame(0, $adapter->getNbResults());
        self::assertSame([], $this->queries);
    }

    /**
     * @test
     */
    public function it_refuses_other_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->source()->resolve(new NoParams(), self::context(new Product()));
    }

    private function source(): ProductAssociationsSource
    {
        return new ProductAssociationsSource($this->visibleProducts(), ProductAssociation::class);
    }

    private static function context(ProductInterface $product): RenderContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return Rendering::context(channel: $channel, hostPageContext: ['product' => $product]);
    }
}
