<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A channel's homepage lives at the channel's root, /{locale}/, Sylius's sylius_shop_homepage route, and its own URL
 * redirects there. So its canonical and hreflang links point there, and so do links to it and the builder's preview of it.
 * See "Rendering the homepage" and "Head tags" in docs/spec/urls-and-seo.md
 */
final readonly class HomepageCanonicalUrlGenerator implements CanonicalUrlGeneratorInterface
{
    public function __construct(
        private CanonicalUrlGeneratorInterface $decorated,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generate(EntryInterface $entry, ChannelInterface $channel, string $localeCode, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): ?string
    {
        if ($entry instanceof PageInterface && $entry->isHomepageOf($channel->getCode() ?? '')) {
            return $this->urlGenerator->generate('sylius_shop_homepage', ['_locale' => $localeCode], $referenceType);
        }

        return $this->decorated->generate($entry, $channel, $localeCode, $referenceType);
    }
}
