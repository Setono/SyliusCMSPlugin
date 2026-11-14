<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Creates the render context of a shop request from Sylius's contexts. This is the one place the read pipeline's
 * channel, locale, currency, customer, time and preview mode come from. In preview mode, the preview's channel comes
 * through Sylius's channel context, and its locale from the token, which also holds when the builder renders blocks
 * through its own endpoint, outside the shop's URLs
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
            $this->previewContext->getPreview()?->localeCode ?? $this->localeContext->getLocaleCode(),
            $this->currencyContext->getCurrencyCode(),
            $customer,
            self::customerGroup($customer),
            $this->clock->now(),
            $hostPageContext,
            $this->previewContext->getMode(),
        );
    }

    /**
     * A customer group code, or one of the reserved values "@guest" and "@no_group"
     */
    private static function customerGroup(?CustomerInterface $customer): string
    {
        if (null === $customer) {
            return '@guest';
        }

        return $customer->getGroup()?->getCode() ?? '@no_group';
    }
}
