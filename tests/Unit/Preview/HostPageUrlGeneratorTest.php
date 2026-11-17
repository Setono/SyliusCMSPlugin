<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preview\HostPageUrlGenerator;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Which real shop page a snippet's preview shows a placement on. See "Snippet preview" in docs/spec/builder.md
 */
final class HostPageUrlGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_shows_a_slot_on_product_pages_on_the_sample_product_s_page(): void
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('mug');
        $product->addTranslation($translation);

        self::assertSame(
            'http://shop.example/en_US/products/mug',
            $this->generator()->generate(self::slot('product.after_price', ['product']), $product, new Channel(), 'en_US'),
        );
        self::assertNull($this->generator()->generate(self::slot('product.after_price', ['product']), $product, new Channel(), 'da_DK'), 'It has no page in a locale it has no slug in');
    }

    /**
     * @test
     */
    public function it_shows_a_slot_on_taxon_pages_on_the_sample_taxon_s_page(): void
    {
        $taxon = new Taxon();
        $taxon->setCurrentLocale('en_US');
        $translation = new TaxonTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('shoes');
        $taxon->addTranslation($translation);

        self::assertSame(
            'http://shop.example/en_US/taxons/shoes',
            $this->generator()->generate(self::slot('taxon.header', ['taxon']), $taxon, new Channel(), 'en_US'),
        );
    }

    /**
     * @test
     */
    public function it_shows_a_layout_s_slot_on_the_sample_entry_s_page(): void
    {
        $page = new Page();
        $channel = new Channel();
        $canonicalUrls = $this->prophesize(CanonicalUrlGeneratorInterface::class);
        $canonicalUrls->generate($page, $channel, 'en_US')->willReturn('http://shop.example/en_US/about-us');

        self::assertSame(
            'http://shop.example/en_US/about-us',
            $this->generator($canonicalUrls->reveal())->generate(self::slot('app.promo.after_blocks', ['entry']), $page, $channel, 'en_US'),
        );
    }

    /**
     * @test
     */
    public function it_has_no_page_for_a_slot_on_product_pages_without_a_sample_product(): void
    {
        self::assertNull($this->generator()->generate(self::slot('product.after_price', ['product']), null, new Channel(), 'en_US'));
        self::assertNull($this->generator()->generate(self::slot('product.after_price', ['product']), new Taxon(), new Channel(), 'en_US'));
    }

    /**
     * The shop.* slots are on every page, and store_homepage on Sylius's homepage
     *
     * @test
     */
    public function it_shows_a_slot_of_the_shop_s_layout_or_its_homepage_on_the_homepage(): void
    {
        foreach (['sylius.shop.layout.footer', 'sylius.shop.layout.header', 'sylius.shop.homepage'] as $event) {
            self::assertSame(
                'http://shop.example/en_US/',
                $this->generator()->generate(new SlotMetadata('shop.footer', 'label', [], null, $event), null, new Channel(), 'en_US'),
            );
        }
    }

    /**
     * Such as the checkout's sidebar, which needs a cart, or a project's slot rendered with cms_slot()
     *
     * @test
     */
    public function it_knows_no_page_for_another_slot_that_provides_nothing(): void
    {
        self::assertNull($this->generator()->generate(new SlotMetadata('checkout.sidebar', 'label', [], null, 'sylius.shop.checkout.sidebar'), null, new Channel(), 'en_US'));
        self::assertNull($this->generator()->generate(new SlotMetadata('app.top', 'label'), null, new Channel(), 'en_US'));
    }

    private function generator(?CanonicalUrlGeneratorInterface $canonicalUrls = null): HostPageUrlGenerator
    {
        $router = $this->prophesize(UrlGeneratorInterface::class);
        $router->generate('sylius_shop_product_show', Argument::type('array'), UrlGeneratorInterface::ABSOLUTE_URL)->will(
            static fn (array $arguments): string => sprintf('http://shop.example/%s/products/%s', $arguments[1]['_locale'], $arguments[1]['slug']),
        );
        $router->generate('sylius_shop_product_index', Argument::type('array'), UrlGeneratorInterface::ABSOLUTE_URL)->will(
            static fn (array $arguments): string => sprintf('http://shop.example/%s/taxons/%s', $arguments[1]['_locale'], $arguments[1]['slug']),
        );
        $router->generate('sylius_shop_homepage', Argument::type('array'), UrlGeneratorInterface::ABSOLUTE_URL)->will(
            static fn (array $arguments): string => sprintf('http://shop.example/%s/', $arguments[1]['_locale']),
        );

        return new HostPageUrlGenerator($router->reveal(), $canonicalUrls ?? $this->prophesize(CanonicalUrlGeneratorInterface::class)->reveal());
    }

    /**
     * @param list<string> $provides
     */
    private static function slot(string $identifier, array $provides): SlotMetadata
    {
        return new SlotMetadata($identifier, 'label', $provides, null, 'sylius.shop.product.show.right_sidebar');
    }
}
