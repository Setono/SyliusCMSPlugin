<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The one place the canonical URL of an entry comes from: the head tags' canonical link and the hreflang links of
 * the other locales both point at it. Decorate it to change where an entry's pages live, as a homepage's does.
 * See "Head tags" in docs/spec/urls-and-seo.md
 */
interface CanonicalUrlGeneratorInterface
{
    /**
     * The entry's own absolute URL in the channel and the locale, without a query string. Null when it has none there,
     * because its content type has no URL of its own or it has no slug in the locale
     */
    public function generate(EntryInterface $entry, ChannelInterface $channel, string $localeCode): ?string;
}
