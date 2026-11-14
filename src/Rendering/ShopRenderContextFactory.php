<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Creates the render context of a shop request from Sylius's contexts. This is the one place the read pipeline's
 * channel, locale, currency, customer, time and preview mode come from. In preview mode, the preview's channel comes
 * through Sylius's channel context, and its locale through the URL
 */
final readonly class ShopRenderContextFactory implements RenderContextFactoryInterface
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private CurrencyContextInterface $currencyContext,
        private CustomerContextInterface $customerContext,
        private ClockInterface $clock,
        private PreviewContext $previewContext,
    ) {
    }

    public function create(array $hostPageContext = []): RenderContext
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            throw new \LogicException(sprintf('The shop channel must implement %s', ChannelInterface::class));
        }

        $customer = $this->customerContext->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            $customer = null;
        }

        return new RenderContext(
            $channel,
            $this->localeContext->getLocaleCode(),
            $this->currencyContext->getCurrencyCode(),
            $customer,
            CustomerGroupValue::of($customer),
            $this->clock->now(),
            $hostPageContext,
            $this->previewContext->getMode(),
        );
    }
}
