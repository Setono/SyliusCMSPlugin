<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Finds the route that takes an entry URL: a Sylius or app route that the router matches before the entry route.
 * See "Reserved URLs" and "Routes added later" in docs/spec/urls-and-seo.md
 */
interface RouteCheckerInterface
{
    /**
     * The name of the route, other than setono_sylius_cms_shop_entry, that matches a GET of the entry URL with this
     * path in the channel and locale, or null when no other route does
     *
     * @param ChannelInterface|null $channel the channel whose hostname the URL is matched on, or null for the current one
     * @param string $path "{prefix}/{slug}", or the slug alone for an empty prefix
     */
    public function takenBy(?ChannelInterface $channel, string $localeCode, string $path): ?string;
}
