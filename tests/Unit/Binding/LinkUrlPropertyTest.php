<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Binding\LinkUrlProperty;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Link\Target\ProductLinkTarget;
use Setono\SyliusCMSPlugin\Link\Target\TaxonLinkTarget;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * product.url and taxon.url: the path of the record's shop page, as its link target leads to it. entry.url is in the
 * functional tests. See "Shipped bindable properties" in docs/spec/slots.md
 */
final class LinkUrlPropertyTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_the_path_of_a_product_s_page_in_the_current_locale(): void
    {
        $product = new Product();
        $translation = new ProductTranslation();
        $translation->setLocale('da_DK');
        $translation->setName('T-shirt');
        $translation->setSlug('t-shirt');
        $product->addTranslation($translation);

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_shop_product_show', ['_locale' => 'da_DK', 'slug' => 't-shirt'])->willReturn('/da_DK/products/t-shirt');
        $property = new LinkUrlProperty(new ProductLinkTarget($this->prophesize(ReferenceTypeLoaderInterface::class)->reveal(), $urlGenerator->reveal(), $this->prophesize(SearchInterface::class)->reveal()));

        self::assertSame('/da_DK/products/t-shirt', $property->resolve($product, new NoParams(), Rendering::context(localeCode: 'da_DK')));
        self::assertNull($property->resolve($product, new NoParams(), Rendering::context(localeCode: 'en_US')), 'A product without a translation in the locale has no page in it');
    }

    /**
     * @test
     */
    public function it_gives_the_path_of_a_taxon_s_product_listing(): void
    {
        $taxon = new Taxon();
        $translation = new TaxonTranslation();
        $translation->setLocale('en_US');
        $translation->setName('Shirts');
        $translation->setSlug('shirts');
        $taxon->addTranslation($translation);

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_shop_product_index', ['_locale' => 'en_US', 'slug' => 'shirts'])->willReturn('/en_US/taxons/shirts');
        $property = new LinkUrlProperty(new TaxonLinkTarget($this->prophesize(ReferenceTypeLoaderInterface::class)->reveal(), $urlGenerator->reveal(), $this->prophesize(SearchInterface::class)->reveal()));

        self::assertSame('/en_US/taxons/shirts', $property->resolve($taxon, new NoParams(), Rendering::context()));
    }
}
