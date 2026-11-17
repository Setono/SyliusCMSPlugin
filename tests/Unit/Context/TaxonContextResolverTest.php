<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Context\TaxonContextResolver;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The taxon resolver finds the taxon from the taxon page's URL, as Sylius's taxon page does. See "Context resolvers" in
 * docs/spec/slots.md
 */
final class TaxonContextResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TaxonRepositoryInterface<Taxon>> */
    private ObjectProphecy $taxons;

    protected function setUp(): void
    {
        $this->taxons = $this->prophesize(TaxonRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_finds_the_taxon_of_the_taxon_page_by_its_slug_in_the_locale(): void
    {
        $taxon = new Taxon();
        $this->taxons->findOneBySlug('category/t-shirts', 'en_US')->willReturn($taxon);

        self::assertSame($taxon, $this->resolver()->resolve(self::request('sylius_shop_product_index', 'category/t-shirts')));
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_slug_no_enabled_taxon_has(): void
    {
        $this->taxons->findOneBySlug('gone', 'en_US')->willReturn(null);

        self::assertNull($this->resolver()->resolve(self::request('sylius_shop_product_index', 'gone')));
    }

    /**
     * @test
     */
    public function it_finds_nothing_on_a_page_that_is_not_a_taxon_page(): void
    {
        $this->taxons->findOneBySlug(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->resolver()->resolve(self::request('sylius_shop_product_show', 't-shirt')));
        self::assertNull($this->resolver()->resolve(self::request('sylius_shop_product_index', null)));
        self::assertNull($this->resolver()->resolve(Request::create('/')));
    }

    private function resolver(): TaxonContextResolver
    {
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        return new TaxonContextResolver($this->taxons->reveal(), $localeContext->reveal());
    }

    private static function request(string $route, ?string $slug): Request
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        if (null !== $slug) {
            $request->attributes->set('slug', $slug);
        }

        return $request;
    }
}
