<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The one place an entry's URL comes from: the head tags' canonical link and the hreflang links of the other locales
 * point at it as an absolute URL, and links to the entry, cms_url() among them, lead to its path, as Sylius's path()
 * gives. Decorate it to change where an entry's pages live, as a homepage's does. See "Head tags" in
 * docs/spec/urls-and-seo.md and "Link targets" in docs/spec/extending.md
 */
interface CanonicalUrlGeneratorInterface
{
    /**
     * The entry's own URL in the channel and the locale, without a query string. Null when it has none there, because
     * its content type has no URL of its own or it has no slug in the locale
     *
     * @param UrlGeneratorInterface::ABSOLUTE_URL|UrlGeneratorInterface::ABSOLUTE_PATH $referenceType an absolute URL,
     *                                                                                               the canonical one,
     *                                                                                               or its path
     */
    public function generate(EntryInterface $entry, ChannelInterface $channel, string $localeCode, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): ?string;
}
