<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Product;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Binding\Product\PriceProperty;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatter;
use Sylius\Component\Core\Calculator\ProductVariantPriceCalculator;
use Sylius\Component\Core\Checker\ProductVariantLowestPriceDisplayCheckerInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

/**
 * product.price and product.original_price: the first enabled variant's price in the channel, formatted in the current
 * currency. See "Shipped bindable properties" in docs/spec/slots.md
 */
final class PricePropertyTest extends TestCase
{
    use ProphecyTrait;

    private Channel $channel;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        $currency = new Currency();
        $currency->setCode('USD');

        $this->channel = new Channel();
        $this->channel->setCode('WEB');
        $this->channel->setBaseCurrency($currency);

        $this->product = new Product();
        $this->variant = new ProductVariant();
        $this->product->addVariant($this->variant);
    }

    /**
     * @test
     */
    public function it_formats_the_price_of_the_resolved_variant_in_the_channel_in_the_current_currency(): void
    {
        $this->priceIn($this->channel, 1999);

        self::assertSame('$19.99', $this->property()->resolve($this->product, new NoParams(), Rendering::context(channel: $this->channel)));
    }

    /**
     * @test
     */
    public function it_formats_the_price_in_the_shopper_s_locale(): void
    {
        $this->priceIn($this->channel, 1999);

        // ICU versions differ in the spaces and symbol they put around it
        self::assertStringContainsString('19,99', (string) $this->property()->resolve($this->product, new NoParams(), Rendering::context(localeCode: 'da_DK', channel: $this->channel)));
    }

    /**
     * @test
     */
    public function it_converts_the_price_from_the_channel_s_base_currency_into_the_current_one(): void
    {
        $this->priceIn($this->channel, 1000);
        $converter = $this->prophesize(CurrencyConverterInterface::class);
        $converter->convert(1000, 'USD', 'EUR')->willReturn(920);

        $value = $this->property(converter: $converter->reveal())->resolve($this->product, new NoParams(), Rendering::context(channel: $this->channel, currencyCode: 'EUR'));

        self::assertSame('€9.20', $value);
    }

    /**
     * @test
     */
    public function it_gives_the_original_price_formatted_the_same_way(): void
    {
        $this->priceIn($this->channel, 1500, 2000);

        self::assertSame('$20.00', $this->property(original: true)->resolve($this->product, new NoParams(), Rendering::context(channel: $this->channel)));
    }

    /**
     * @test
     */
    public function it_gives_an_empty_original_price_when_the_variant_has_none(): void
    {
        $this->priceIn($this->channel, 1500);

        self::assertNull($this->property(original: true)->resolve($this->product, new NoParams(), Rendering::context(channel: $this->channel)));
    }

    /**
     * @test
     */
    public function it_is_empty_without_a_price_in_the_channel(): void
    {
        $other = new Channel();
        $other->setCode('OUTLET');
        $this->priceIn($other, 1500);

        self::assertNull($this->property()->resolve($this->product, new NoParams(), Rendering::context(channel: $this->channel)));
    }

    /**
     * @test
     */
    public function it_is_empty_without_an_enabled_variant(): void
    {
        $resolver = $this->prophesize(ProductVariantResolverInterface::class);
        $resolver->getVariant($this->product)->willReturn(null);

        self::assertNull($this->property(resolver: $resolver->reveal())->resolve($this->product, new NoParams(), Rendering::context(channel: $this->channel)));
    }

    private function priceIn(Channel $channel, int $price, ?int $originalPrice = null): void
    {
        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode($channel->getCode());
        $channelPricing->setPrice($price);
        $channelPricing->setOriginalPrice($originalPrice);
        $this->variant->addChannelPricing($channelPricing);
    }

    private function property(bool $original = false, ?ProductVariantResolverInterface $resolver = null, ?CurrencyConverterInterface $converter = null): PriceProperty
    {
        if (null === $resolver) {
            $prophecy = $this->prophesize(ProductVariantResolverInterface::class);
            $prophecy->getVariant($this->product)->willReturn($this->variant);
            $resolver = $prophecy->reveal();
        }

        return new PriceProperty(
            $resolver,
            new ProductVariantPriceCalculator($this->prophesize(ProductVariantLowestPriceDisplayCheckerInterface::class)->reveal()),
            $converter ?? $this->prophesize(CurrencyConverterInterface::class)->reveal(),
            new MoneyFormatter(),
            $original,
        );
    }
}
