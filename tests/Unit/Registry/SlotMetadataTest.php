<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

final class SlotMetadataTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideWrapperClasses
     */
    public function it_wraps_what_it_shows_in_cms_slot_and_a_modifier_of_its_identifier_with_dots_and_underscores_as_hyphens(string $identifier, string $class): void
    {
        self::assertSame($class, (new SlotMetadata($identifier, 'app.label'))->wrapperClass());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function provideWrapperClasses(): iterable
    {
        yield 'one code' => ['store_homepage', 'cms-slot cms-slot--store-homepage'];
        yield 'two codes' => ['shop.footer', 'cms-slot cms-slot--shop-footer'];
        yield 'dots and underscores' => ['product.after_price', 'cms-slot cms-slot--product-after-price'];
        yield 'three codes' => ['app.store_locator.top', 'cms-slot cms-slot--app-store-locator-top'];
    }

    /**
     * @test
     */
    public function it_provides_a_set_of_context_keys_when_it_provides_each_of_them(): void
    {
        $productPage = new SlotMetadata('product.sidebar', 'app.label', ['product']);

        self::assertTrue($productPage->providesAll([]));
        self::assertTrue($productPage->providesAll(['product']));
        self::assertFalse($productPage->providesAll(['product', 'taxon']));
        self::assertFalse((new SlotMetadata('shop.footer', 'app.label'))->providesAll(['product']));
    }

    /**
     * A channel with a CMS homepage doesn't render Sylius's own homepage, nor the slots tied to its template event. See
     * "Rendering the homepage" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_is_on_sylius_s_own_homepage_when_it_is_tied_to_the_homepage_s_template_event(): void
    {
        self::assertTrue((new SlotMetadata('store_homepage', 'app.label', templateEvent: 'sylius.shop.homepage', templateEventPriority: 100))->isOnStoreHomepage());
        self::assertTrue((new SlotMetadata('app.homepage_promo', 'app.label', templateEvent: 'sylius.shop.homepage'))->isOnStoreHomepage());
        self::assertFalse((new SlotMetadata('shop.top', 'app.label', templateEvent: 'sylius.shop.layout.header'))->isOnStoreHomepage());
        self::assertFalse((new SlotMetadata('app.store_locator.top', 'app.label'))->isOnStoreHomepage());
    }

    /**
     * @test
     *
     * @dataProvider provideIdentifiers
     */
    public function it_tells_an_identifier_by_its_codes_joined_by_dots(string $identifier, bool $valid): void
    {
        self::assertSame($valid, SlotMetadata::isIdentifier($identifier));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public function provideIdentifiers(): iterable
    {
        yield 'one code' => ['store_homepage', true];
        yield 'codes joined by dots' => ['app.store_locator.top', true];
        yield 'digits after the first letter' => ['app.top2', true];
        yield 'empty' => ['', false];
        yield 'a capital' => ['Shop.top', false];
        yield 'a hyphen' => ['shop.after-price', false];
        yield 'a leading digit' => ['shop.2top', false];
        yield 'two dots in a row' => ['shop..top', false];
        yield 'a trailing dot' => ['shop.', false];
        yield 'a template event name with a space' => ['sylius shop', false];
    }
}
