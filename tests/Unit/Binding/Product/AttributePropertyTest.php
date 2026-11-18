<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Product;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Binding\Product\AttributeParams;
use Setono\SyliusCMSPlugin\Binding\Product\AttributeProperty;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * product.attribute: the value of the attribute its parameter names by code, as text. See "Shipped bindable
 * properties" in docs/spec/slots.md
 */
final class AttributePropertyTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_the_value_of_the_attribute_its_parameter_names_by_code(): void
    {
        $product = new Product();
        $product->addAttribute(self::value('material', 'text', 'Cotton', 'en_US'));
        $product->addAttribute(self::value('fit', 'text', 'Slim', 'en_US'));

        self::assertSame('Cotton', self::property()->resolve($product, self::params('material'), Rendering::context()));
        self::assertSame('Slim', self::property()->resolve($product, self::params('fit'), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_gives_the_value_in_the_locale_and_falls_back_along_the_locale_s_fallback_chain(): void
    {
        $product = new Product();
        $product->addAttribute(self::value('material', 'text', 'Cotton', 'en_US'));
        $product->addAttribute(self::value('material', 'text', 'Bomuld', 'da_DK'));

        self::assertSame('Bomuld', self::property()->resolve($product, self::params('material'), Rendering::context(localeCode: 'da_DK')));
        self::assertSame('Cotton', self::property()->resolve($product, self::params('material'), Rendering::context(localeCode: 'de_DE')));
    }

    /**
     * @test
     */
    public function it_gives_a_number_as_text(): void
    {
        $product = new Product();
        $product->addAttribute(self::value('weight', 'integer', 250, null));

        self::assertSame('250', self::property()->resolve($product, self::params('weight'), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_gives_the_labels_of_a_select_attribute_s_choices_in_the_locale(): void
    {
        $value = self::value('colours', 'select', ['c1', 'c2'], null);
        $value->getAttribute()?->setConfiguration(['choices' => [
            'c1' => ['en_US' => 'Red', 'da_DK' => 'Rød'],
            'c2' => ['en_US' => 'Blue'],
        ]]);
        $product = new Product();
        $product->addAttribute($value);

        self::assertSame('Rød, Blue', self::property()->resolve($product, self::params('colours'), Rendering::context(localeCode: 'da_DK')));
    }

    /**
     * @test
     */
    public function it_is_empty_when_the_product_has_no_value_for_the_attribute(): void
    {
        $product = new Product();
        $product->addAttribute(self::value('material', 'text', 'Cotton', 'en_US'));

        self::assertNull(self::property()->resolve($product, self::params('fit'), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_is_empty_without_an_attribute_to_show(): void
    {
        self::assertNull(self::property()->resolve(new Product(), new AttributeParams(), Rendering::context()));
        self::assertNull(self::property()->resolve(new Product(), new NoParams(), Rendering::context()));
    }

    private static function value(string $code, string $type, mixed $stored, ?string $locale): ProductAttributeValue
    {
        $attribute = new ProductAttribute();
        $attribute->setCode($code);
        $attribute->setType($type);
        $attribute->setStorageType(match ($type) {
            'integer' => 'integer',
            'select' => 'json',
            default => 'text',
        });

        $value = new ProductAttributeValue();
        $value->setAttribute($attribute);
        $value->setLocaleCode($locale);
        $value->setValue($stored);

        return $value;
    }

    private static function params(string $attribute): AttributeParams
    {
        $params = new AttributeParams();
        $params->attribute = $attribute;

        return $params;
    }

    private static function property(): AttributeProperty
    {
        return new AttributeProperty(
            new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US', 'da_DK', 'de_DE'], 'en_US')),
            new IdentityTranslator(),
        );
    }
}
