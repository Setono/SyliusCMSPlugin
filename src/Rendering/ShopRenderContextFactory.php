<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Psr\Clock\ClockInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Creates the render context of a shop request from Sylius's contexts. This is the one place the read pipeline's
 * channel, locale, currency, customer and time come from
 */
final readonly class ShopRenderContextFactory implements RenderContextFactoryInterface
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private CurrencyContextInterface $currencyContext,
        private CustomerContextInterface $customerContext,
        private ClockInterface $clock,
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
            self::customerGroup($customer),
            $this->clock->now(),
            $hostPageContext,
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
