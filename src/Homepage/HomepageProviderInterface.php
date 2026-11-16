<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Finds the page a channel shows at its root URL. A project can add a cache by decorating it and invalidating on
 * EntrySettingsChanged and the other StorefrontChangedInterface events. See "Homepage" in docs/spec/urls-and-seo.md
 */
interface HomepageProviderInterface
{
    /**
     * The homepage of the context's channel when the shopper can see it in the context's locale: it's visible, as
     * "When an entry shows" in docs/spec/content-model.md has it. Null when the channel has no homepage, or the shopper
     * can't see it, and Sylius's own homepage shows
     */
    public function getHomepage(RenderContext $context): ?PageInterface;
}
