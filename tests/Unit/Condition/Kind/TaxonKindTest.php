<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\TaxonKind;
use Setono\SyliusCMSPlugin\Condition\Kind\TaxonParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;

final class TaxonKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_when_the_page_s_taxon_is_one_of_the_taxons(): void
    {
        $params = self::params(self::taxon('SHOES'), self::taxon('JEANS'));

        self::assertTrue((new TaxonKind())->matches($params, self::onTaxonPage(self::taxon('SHOES'))));
        self::assertFalse((new TaxonKind())->matches($params, self::onTaxonPage(self::taxon('T_SHIRTS'))));
    }

    /**
     * Only the taxon itself: a taxon below one of them is another taxon page
     *
     * @test
     */
    public function it_doesn_t_match_a_taxon_below_one_of_the_taxons(): void
    {
        $shoes = self::taxon('SHOES');
        $sneakers = self::taxon('SNEAKERS');
        $shoes->addChild($sneakers);

        self::assertFalse((new TaxonKind())->matches(self::params($shoes), self::onTaxonPage($sneakers)));
    }

    /**
     * @test
     */
    public function it_matches_nothing_without_taxons(): void
    {
        self::assertFalse((new TaxonKind())->matches(self::params(), self::onTaxonPage(self::taxon('SHOES'))));
        self::assertFalse((new TaxonKind())->matches(new TaxonParams(), self::onTaxonPage(self::taxon('SHOES'))));
    }

    /**
     * @test
     */
    public function it_matches_nothing_on_a_page_without_a_taxon(): void
    {
        self::assertFalse((new TaxonKind())->matches(self::params(self::taxon('SHOES')), Rendering::context()));
        self::assertFalse((new TaxonKind())->matches(self::params(self::taxon('SHOES')), Rendering::context(hostPageContext: ['taxon' => new Product()])), 'Not a taxon');
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The taxon condition takes %s, got %s', TaxonParams::class, NoParams::class));

        (new TaxonKind())->matches(new NoParams(), self::onTaxonPage(self::taxon('SHOES')));
    }

    private static function params(TaxonInterface ...$taxons): TaxonParams
    {
        $params = new TaxonParams();
        $params->taxons = ItemList::of(array_values($taxons));

        return $params;
    }

    private static function taxon(string $code): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCode($code);

        return $taxon;
    }

    private static function onTaxonPage(Taxon $taxon): RenderContext
    {
        return Rendering::context(hostPageContext: ['taxon' => $taxon]);
    }
}
