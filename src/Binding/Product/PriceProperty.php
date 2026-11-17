<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding\Product;

use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

/**
 * A product's price, or its original price: that of the variant Sylius's default variant resolver picks, its first
 * enabled one, in the channel, converted from the channel's base currency into the current currency and formatted in
 * it, as the shop's templates show prices. It's empty without such a variant or a price in the channel, and the
 * original price is empty when the variant has none. The shipped bindable properties product.price and
 * product.original_price are declared with it. See "Shipped bindable properties" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class PriceProperty implements BindablePropertyInterface
{
    /**
     * @param bool $original whether it's the original price, before any discount
     */
    public function __construct(
        private ProductVariantResolverInterface $variantResolver,
        private ProductVariantPricesCalculatorInterface $priceCalculator,
        private CurrencyConverterInterface $currencyConverter,
        private MoneyFormatterInterface $moneyFormatter,
        private bool $original = false,
    ) {
    }

    public function resolve(object $subject, object $params, RenderContext $context): ?string
    {
        if (!$subject instanceof ProductInterface) {
            return null;
        }

        $variant = $this->variantResolver->getVariant($subject);
        if (!$variant instanceof ProductVariantInterface) {
            return null;
        }

        $channelPricing = $variant->getChannelPricingForChannel($context->channel);
        if (null === $channelPricing || null === $channelPricing->getPrice()) {
            return null;
        }

        $amount = $this->original ? $channelPricing->getOriginalPrice() : $this->priceCalculator->calculate($variant, ['channel' => $context->channel]);
        if (null === $amount) {
            return null;
        }

        $baseCurrency = $context->channel->getBaseCurrency()?->getCode();
        if (null !== $baseCurrency && $baseCurrency !== $context->currencyCode) {
            $amount = $this->currencyConverter->convert($amount, $baseCurrency, $context->currencyCode);
        }

        return $this->moneyFormatter->format($amount, $context->currencyCode, $context->localeCode);
    }
}
