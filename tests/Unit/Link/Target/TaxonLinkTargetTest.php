<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Target;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Link\Target\TaxonLinkTarget;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TaxonLinkTargetTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ReferenceTypeLoaderInterface> */
    private ObjectProphecy $taxons;

    /** @var ObjectProphecy<UrlGeneratorInterface> */
    private ObjectProphecy $urlGenerator;

    /** @var ObjectProphecy<SearchInterface> */
    private ObjectProphecy $search;

    protected function setUp(): void
    {
        $this->taxons = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $this->urlGenerator
            ->generate('sylius_shop_product_index', Argument::type('array'), UrlGeneratorInterface::ABSOLUTE_URL)
            ->will(static function (array $arguments): string {
                $parameters = $arguments[1];
                \assert(is_array($parameters) && is_string($parameters['_locale']) && is_string($parameters['slug']));

                return sprintf('https://localhost/%s/taxons/%s', $parameters['_locale'], $parameters['slug']);
            })
        ;
        $this->search = $this->prophesize(SearchInterface::class);
    }

    /**
     * @test
     */
    public function it_is_the_taxon_target_whose_ref_is_shared(): void
    {
        $attribute = AttributeReader::linkTarget(TaxonLinkTarget::class);

        self::assertSame('taxon', $attribute?->code);
        self::assertFalse($attribute->translatableRef);
    }

    /**
     * @test
     */
    public function it_resolves_an_enabled_taxon_to_its_product_listing_named_by_its_name_in_the_locale(): void
    {
        $context = $this->context('en_US');
        $this->taxons->load(['T_SHIRTS'], $context)->willReturn(['T_SHIRTS' => self::taxon()]);

        self::assertEquals(
            new ResolvedLink('taxon', 'T_SHIRTS', 'https://localhost/en_US/taxons/category/t-shirts', 'T-shirts'),
            $this->target()->resolve('T_SHIRTS', $context),
        );
    }

    /**
     * @test
     */
    public function it_resolves_a_taxon_the_shopper_may_not_see_to_nothing(): void
    {
        $context = $this->context('en_US');
        $this->taxons->load(['T_SHIRTS'], $context)->willReturn([]);

        self::assertNull($this->target()->resolve('T_SHIRTS', $context));
    }

    /**
     * @test
     */
    public function it_leads_nowhere_in_a_locale_the_taxon_has_no_translation_in(): void
    {
        self::assertNull($this->target()->link(self::taxon(), 'T_SHIRTS', $this->context('de_DE')));
    }

    /**
     * @test
     */
    public function it_refers_to_the_taxon_by_its_code_as_a_link(): void
    {
        self::assertEquals(new Reference('taxon', 'T_SHIRTS', false), $this->target()->reference('T_SHIRTS'));
        self::assertNull($this->target()->reference(''));
    }

    /**
     * @test
     */
    public function it_searches_taxons(): void
    {
        $this->search->search('shirt', 'en_US')->willReturn([new SearchResult('T_SHIRTS', 'Category / T-shirts')]);

        self::assertSame([['ref' => 'T_SHIRTS', 'name' => 'Category / T-shirts', 'thumbnail' => null]], [...$this->target()->search('shirt', 'en_US')]);
    }

    private function target(): TaxonLinkTarget
    {
        return new TaxonLinkTarget($this->taxons->reveal(), $this->urlGenerator->reveal(), $this->search->reveal());
    }

    private static function taxon(): Taxon
    {
        $translation = new TaxonTranslation();
        $translation->setLocale('en_US');
        $translation->setName('T-shirts');
        $translation->setSlug('category/t-shirts');

        $taxon = new Taxon();
        $taxon->setCode('T_SHIRTS');
        $taxon->addTranslation($translation);

        return $taxon;
    }

    private function context(string $localeCode): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), $localeCode, 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
