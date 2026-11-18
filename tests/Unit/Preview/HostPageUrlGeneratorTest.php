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
use Setono\SyliusCMSPlugin\Tests\Support\SnippetPreviews;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslation;

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
            'https://shop.example/en_US/products/mug',
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
            'https://shop.example/en_US/taxons/shoes',
            $this->generator()->generate(self::slot('taxon.header', ['taxon']), $taxon, new Channel(), 'en_US'),
        );
    }

    /**
     * @test
     */
    public function it_shows_a_layout_s_slot_on_the_page_of_a_sample_entry_whose_layout_renders_it(): void
    {
        $page = self::page('promo');
        $channel = new Channel();
        $canonicalUrls = $this->prophesize(CanonicalUrlGeneratorInterface::class);
        $canonicalUrls->generate($page, $channel, 'en_US')->willReturn('https://shop.example/en_US/about-us');

        self::assertSame(
            'https://shop.example/en_US/about-us',
            $this->generator($canonicalUrls->reveal())->generate(self::slot('app.promo.after_blocks', ['entry']), $page, $channel, 'en_US'),
        );
    }

    /**
     * Its page wouldn't show the slot, so the preview shows the snippet on a plain page instead
     *
     * @test
     */
    public function it_knows_no_page_for_a_layout_s_slot_when_the_sample_entry_s_layout_does_not_render_it(): void
    {
        $canonicalUrls = $this->prophesize(CanonicalUrlGeneratorInterface::class);
        $canonicalUrls->generate(Argument::cetera())->willReturn('https://shop.example/en_US/about-us');
        $generator = $this->generator($canonicalUrls->reveal());

        self::assertNull($generator->generate(self::slot('app.promo.after_blocks', ['entry']), self::page('standard'), new Channel(), 'en_US'));
        self::assertNull($generator->generate(self::slot('app.promo.after_blocks', ['entry']), self::page(null), new Channel(), 'en_US'), 'In the default layout');
        self::assertNull($generator->generate(self::slot('app.landing.after_hero', ['entry']), self::page('promo'), new Channel(), 'en_US'), 'Another layout\'s slot');
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
                'https://shop.example/en_US/',
                $this->generator()->generate(new SlotMetadata('shop.footer', 'label', [], null, $event), null, new Channel(), 'en_US'),
            );
        }
    }

    /**
     * A CMS homepage replaces Sylius's, so store_homepage isn't on it, while the shop's layout still is. As the admin's
     * warning, a channel counts as soon as a page is its homepage
     *
     * @test
     */
    public function it_knows_no_page_for_a_slot_on_sylius_s_homepage_in_a_channel_whose_homepage_is_a_cms_page(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $generator = SnippetPreviews::hostPageUrls(cmsHomepageChannels: ['WEB']);

        self::assertNull($generator->generate(new SlotMetadata('store_homepage', 'label', [], null, 'sylius.shop.homepage'), null, $channel, 'en_US'));
        self::assertSame('https://shop.example/en_US/', $generator->generate(new SlotMetadata('shop.footer', 'label', [], null, 'sylius.shop.layout.footer'), null, $channel, 'en_US'));

        $other = new Channel();
        $other->setCode('B2B');
        self::assertSame('https://shop.example/en_US/', $generator->generate(new SlotMetadata('store_homepage', 'label', [], null, 'sylius.shop.homepage'), null, $other, 'en_US'));
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
        return SnippetPreviews::hostPageUrls($canonicalUrls);
    }

    /**
     * A page in the layout, or in the default one, standard, for null
     */
    private static function page(?string $layout): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null === $layout ? null : new StoredLayout($layout, 1), []));

        return $page;
    }

    /**
     * @param list<string> $provides
     */
    private static function slot(string $identifier, array $provides): SlotMetadata
    {
        return new SlotMetadata($identifier, 'label', $provides, null, 'sylius.shop.product.show.right_sidebar');
    }
}
