<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The page the context's channel shows at its root URL, /{locale}/, in place of Sylius's own homepage.
 * See "Rendering the homepage" in docs/spec/urls-and-seo.md
 */
interface HomepageResolverInterface
{
    /**
     * Null when Sylius's own homepage shows: the channel has no homepage, or the shopper can't see it
     */
    public function resolve(RenderContext $context): ?PageInterface;
}
