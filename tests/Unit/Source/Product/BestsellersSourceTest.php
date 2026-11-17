<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Product;

use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Model\BestsellerScore;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\BestsellersParams;
use Setono\SyliusCMSPlugin\Source\Product\BestsellersSource;
use Setono\SyliusCMSPlugin\Source\Product\TaxonFilter;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\Taxon;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

/**
 * "Bestsellers": the visible products with a score in the channel, highest first, then by ID, optionally only those in a
 * taxon or below it. See "Product sources" and "Bestsellers" in docs/spec/sources.md
 */
final class BestsellersSourceTest extends TestCase
{
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_bestsellers_source_of_products(): void
    {
        $attribute = AttributeReader::source(BestsellersSource::class);

        self::assertNotNull($attribute);
        self::assertSame('bestsellers', $attribute->code);
        self::assertSame('setono_sylius_cms.source.bestsellers', $attribute->label);
        self::assertSame(ProductInterface::class, $attribute->of);
        self::assertSame([], $attribute->needs);
        self::assertSame(BestsellersParams::class, $attribute->params);
    }

    /**
     * @test
     */
    public function it_pages_the_visible_products_with_a_score_in_the_channel_highest_first_then_by_id_in_sql(): void
    {
        $adapter = $this->source()->resolve(new BestsellersParams(), self::context());

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertSame(sprintf(
            'SELECT product FROM %s product INNER JOIN %s cms_source_score WITH cms_source_score.productCode = product.code AND cms_source_score.channelCode = :cms_source_score_channel_code WHERE product.enabled = :cms_visible_enabled ORDER BY cms_source_score.score DESC, product.id ASC',
            Product::class,
            BestsellerScore::class,
        ), $this->dql());
        self::assertSame('WEB', $this->parameter('cms_source_score_channel_code'));
    }

    /**
     * @test
     */
    public function it_keeps_the_products_in_the_taxon(): void
    {
        $taxon = new Taxon();
        $params = new BestsellersParams();
        $params->taxon = $taxon;

        $this->source()->resolve($params, self::context());

        self::assertStringContainsString('INNER JOIN product.productTaxons cms_source_product_taxon WITH cms_source_product_taxon.taxon = :cms_source_taxon', $this->dql());
        self::assertSame($taxon, $this->parameter('cms_source_taxon'));
        self::assertStringEndsWith('ORDER BY cms_source_score.score DESC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_keeps_the_products_in_the_taxon_or_below_it_with_its_children(): void
    {
        $taxon = new Taxon();
        $taxon->setLeft(2);
        $taxon->setRight(7);
        $params = new BestsellersParams();
        $params->taxon = $taxon;
        $params->includeChildren = true;

        $this->source()->resolve($params, self::context());

        self::assertStringContainsString(sprintf('EXISTS (SELECT cms_source_in_subtree.id FROM %s cms_source_in_subtree', ProductTaxon::class), $this->dql());
        self::assertStringNotContainsString('productTaxons', $this->dql());
        self::assertSame(2, $this->parameter('cms_source_taxon_left'));
        self::assertStringEndsWith('ORDER BY cms_source_score.score DESC, product.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_refuses_other_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->source()->resolve(new NoParams(), self::context());
    }

    private function source(): BestsellersSource
    {
        return new BestsellersSource($this->visibleProducts(), new TaxonFilter(ProductTaxon::class), BestsellerScore::class);
    }

    private static function context(): RenderContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return Rendering::context(channel: $channel);
    }
}
