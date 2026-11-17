<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Taxon;

use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Taxon\TaxonChildrenParams;
use Setono\SyliusCMSPlugin\Source\Taxon\TaxonChildrenSource;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * "Children of a taxon": the visible taxons right below a taxon, by their position, then by ID. See "Taxon sources" in
 * docs/spec/sources.md
 */
final class TaxonChildrenSourceTest extends TestCase
{
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_children_of_a_taxon_source_of_taxons(): void
    {
        $attribute = AttributeReader::source(TaxonChildrenSource::class);

        self::assertNotNull($attribute);
        self::assertSame('taxon_children', $attribute->code);
        self::assertSame('setono_sylius_cms.source.taxon_children', $attribute->label);
        self::assertSame(TaxonInterface::class, $attribute->of);
        self::assertSame([], $attribute->needs);
        self::assertSame(TaxonChildrenParams::class, $attribute->params);
    }

    /**
     * @test
     */
    public function it_pages_the_visible_children_of_the_taxon_by_their_position_then_by_id(): void
    {
        $taxon = new Taxon();
        $params = new TaxonChildrenParams();
        $params->taxon = $taxon;

        $adapter = (new TaxonChildrenSource($this->visibleTaxons()))->resolve($params, self::context());

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertStringContainsString('taxon.enabled = :cms_visible_enabled', $this->dql());
        self::assertStringContainsString('taxon.parent = :cms_source_parent', $this->dql());
        self::assertSame($taxon, $this->parameter('cms_source_parent'));
        self::assertStringEndsWith('ORDER BY taxon.position ASC, taxon.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_without_a_taxon_and_asks_for_no_query(): void
    {
        $adapter = (new TaxonChildrenSource($this->visibleTaxons()))->resolve(new TaxonChildrenParams(), self::context());

        self::assertSame(0, $adapter->getNbResults());
        self::assertSame([], $this->queries);
    }

    /**
     * @test
     */
    public function it_refuses_other_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TaxonChildrenSource($this->visibleTaxons()))->resolve(new NoParams(), self::context());
    }

    private static function context(): RenderContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return Rendering::context(channel: $channel);
    }
}
