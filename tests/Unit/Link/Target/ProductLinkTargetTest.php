<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Target;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Link\Target\ProductLinkTarget;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class ProductLinkTargetTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ReferenceTypeLoaderInterface> */
    private ObjectProphecy $products;

    /** @var ObjectProphecy<SearchInterface> */
    private ObjectProphecy $search;

    protected function setUp(): void
    {
        $this->products = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $this->search = $this->prophesize(SearchInterface::class);
    }

    /**
     * @test
     */
    public function it_is_the_product_target_whose_ref_is_shared(): void
    {
        $attribute = AttributeReader::linkTarget(ProductLinkTarget::class);

        self::assertSame('product', $attribute?->code);
        self::assertFalse($attribute->translatableRef);
    }

    /**
     * @test
     */
    public function it_resolves_a_product_the_shopper_may_see_to_its_page_named_by_its_name_in_the_locale(): void
    {
        $context = $this->context('de_DE');
        $this->products->load(['T_SHIRT'], $context)->willReturn(['T_SHIRT' => self::product()]);

        self::assertEquals(
            new ResolvedLink('product', 'T_SHIRT', '/de_DE/products/rotes-t-shirt', 'Rotes T-Shirt'),
            $this->target()->resolve('T_SHIRT', $context),
        );
    }

    /**
     * @test
     */
    public function it_resolves_a_product_the_shopper_may_not_see_to_nothing(): void
    {
        $context = $this->context('de_DE');
        $this->products->load(['T_SHIRT'], $context)->willReturn([]);

        self::assertNull($this->target()->resolve('T_SHIRT', $context));
    }

    /**
     * @test
     */
    public function it_leads_nowhere_in_a_locale_the_product_has_no_translation_in(): void
    {
        self::assertNull($this->target()->link(self::product(), 'T_SHIRT', $this->context('da_DK')));
    }

    /**
     * @test
     */
    public function it_refers_to_the_product_by_its_code_as_a_link(): void
    {
        self::assertEquals(new Reference('product', 'T_SHIRT', false), $this->target()->reference('T_SHIRT'));
        self::assertNull($this->target()->reference(''));
    }

    /**
     * @test
     */
    public function it_searches_products(): void
    {
        $this->search->search('shirt', 'en_US')->willReturn([new SearchResult('T_SHIRT', 'Red T-shirt')]);

        self::assertSame([['ref' => 'T_SHIRT', 'name' => 'Red T-shirt', 'thumbnail' => null]], [...$this->target()->search('shirt', 'en_US')]);
    }

    private function target(): ProductLinkTarget
    {
        // A real generator on the shop's host, so a link's URL shows whether it's a path or an absolute URL
        $routes = new RouteCollection();
        $routes->add('sylius_shop_product_show', new Route('/{_locale}/products/{slug}', requirements: ['slug' => '.+(?<!/)']));
        $urlGenerator = new UrlGenerator($routes, new RequestContext(host: 'localhost', scheme: 'https'));

        return new ProductLinkTarget($this->products->reveal(), $urlGenerator, $this->search->reveal());
    }

    private static function product(): Product
    {
        $product = new Product();
        $product->setCode('T_SHIRT');
        foreach (['en_US' => ['Red T-shirt', 'red-t-shirt'], 'de_DE' => ['Rotes T-Shirt', 'rotes-t-shirt']] as $locale => [$name, $slug]) {
            $translation = new ProductTranslation();
            $translation->setLocale($locale);
            $translation->setName($name);
            $translation->setSlug($slug);
            $product->addTranslation($translation);
        }

        return $product;
    }

    private function context(string $localeCode): RenderContext
    {
        return Rendering::context(localeCode: $localeCode);
    }
}
