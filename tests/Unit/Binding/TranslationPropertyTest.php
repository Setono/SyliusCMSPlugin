<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Binding\TranslationProperty;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslation;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;

/**
 * product.name, product.short_description, product.description, product.main_taxon_name, taxon.name,
 * taxon.description and entry.title: a translated field in the current locale, along its fallback chain. See "Shipped
 * bindable properties" in docs/spec/slots.md
 */
final class TranslationPropertyTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_the_product_s_name_short_description_and_description_in_the_current_locale(): void
    {
        $product = self::product(['en_US' => ['T-shirt', 'Soft', 'A soft T-shirt'], 'da_DK' => ['T-shirt (da)', 'Blød', 'En blød T-shirt']]);
        $context = Rendering::context(localeCode: 'da_DK');

        self::assertSame('T-shirt (da)', self::property('name')->resolve($product, new NoParams(), $context));
        self::assertSame('Blød', self::property('shortDescription')->resolve($product, new NoParams(), $context));
        self::assertSame('En blød T-shirt', self::property('description')->resolve($product, new NoParams(), $context));
    }

    /**
     * @test
     */
    public function it_falls_back_along_the_locale_s_fallback_chain_past_a_missing_or_empty_translation(): void
    {
        $product = self::product(['en_US' => ['T-shirt', 'Soft', 'A soft T-shirt'], 'da_DK' => ['T-shirt (da)', '', null]]);

        self::assertSame('Soft', self::property('shortDescription')->resolve($product, new NoParams(), Rendering::context(localeCode: 'da_DK')));
        self::assertSame('T-shirt', self::property('name')->resolve($product, new NoParams(), Rendering::context(localeCode: 'de_DE')));
        self::assertCount(2, $product->getTranslations(), 'A missing translation is never created');
    }

    /**
     * @test
     */
    public function it_is_empty_when_no_translation_has_a_value(): void
    {
        self::assertNull(self::property('description')->resolve(self::product(['en_US' => ['T-shirt', null, null]]), new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_gives_the_name_of_the_product_s_main_taxon(): void
    {
        $taxon = new Taxon();
        $translation = new TaxonTranslation();
        $translation->setLocale('en_US');
        $translation->setName('Shirts');
        $taxon->addTranslation($translation);
        $product = self::product(['en_US' => ['T-shirt', null, null]]);
        $product->setMainTaxon($taxon);

        self::assertSame('Shirts', self::property('name', 'mainTaxon')->resolve($product, new NoParams(), Rendering::context()));
        self::assertSame('Shirts', self::property('name')->resolve($taxon, new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_is_empty_for_a_product_without_a_main_taxon(): void
    {
        self::assertNull(self::property('name', 'mainTaxon')->resolve(self::product(['en_US' => ['T-shirt', null, null]]), new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_gives_the_title_of_an_entry(): void
    {
        $page = new Page();
        $translation = new PageTranslation();
        $translation->setLocale('en_US');
        $translation->setTitle('About us');
        $page->addTranslation($translation);

        self::assertSame('About us', self::property('title')->resolve($page, new NoParams(), Rendering::context(localeCode: 'da_DK')));
    }

    /**
     * @test
     */
    public function it_is_empty_for_a_subject_without_translations(): void
    {
        self::assertNull(self::property('name')->resolve(new Channel(), new NoParams(), Rendering::context()));
    }

    /**
     * @param array<string, array{string, string|null, string|null}> $translations locale => [name, short description, description]
     */
    private static function product(array $translations): Product
    {
        $product = new Product();
        foreach ($translations as $locale => [$name, $shortDescription, $description]) {
            $translation = new ProductTranslation();
            $translation->setLocale($locale);
            $translation->setName($name);
            $translation->setShortDescription($shortDescription);
            $translation->setDescription($description);
            $product->addTranslation($translation);
        }

        return $product;
    }

    private static function property(string $field, ?string $of = null): TranslationProperty
    {
        return new TranslationProperty($field, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US', 'da_DK', 'de_DE'], 'en_US')), $of);
    }
}
