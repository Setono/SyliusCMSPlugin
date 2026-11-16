<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The page the context's channel shows at its root URL, /{locale}/, in place of Sylius's own homepage. In the
 * builder's preview of the channel's homepage, that's its working copy, whatever its own visibility, so the builder
 * previews a homepage where shoppers see it. See "Rendering the homepage" in docs/spec/urls-and-seo.md
 */
final readonly class HomepageResolver implements HomepageResolverInterface
{
    public function __construct(
        private HomepageProviderInterface $homepages,
        private PreviewedEntryProvider $previewedEntryProvider,
    ) {
    }

    /**
     * Null when Sylius's own homepage shows: the channel has no homepage, or the shopper can't see it
     */
    public function resolve(RenderContext $context): ?PageInterface
    {
        $previewed = $this->previewedEntryProvider->getWorkingCopy($context->localeCode);
        if ($previewed instanceof PageInterface && $previewed->isHomepageOf($context->channel->getCode() ?? '')) {
            return $previewed;
        }

        return $this->homepages->getHomepage($context);
    }
}
