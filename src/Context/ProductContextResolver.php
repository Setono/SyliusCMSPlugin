<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The product on Sylius's product page, found from its route and slug as the page finds it: enabled, in the channel, by
 * its slug in the locale. So a slot whose template event doesn't pass the product, such as product.top in a layout event
 * that fires on every page, shows only on product pages. See "Context resolvers" in docs/spec/slots.md
 */
#[AsContextResolver('product')]
final readonly class ProductContextResolver implements ContextResolverInterface
{
    /** Sylius's product page */
    public const ROUTE = 'sylius_shop_product_show';

    /**
     * @param ProductRepositoryInterface<ProductInterface> $products
     */
    public function __construct(
        private ProductRepositoryInterface $products,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function resolve(Request $request): ?object
    {
        $slug = $request->attributes->get('slug');
        if (self::ROUTE !== $request->attributes->get('_route') || !is_string($slug)) {
            return null;
        }

        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return null;
        }

        return $this->products->findOneByChannelAndSlug($channel, $this->localeContext->getLocaleCode(), $slug);
    }
}
