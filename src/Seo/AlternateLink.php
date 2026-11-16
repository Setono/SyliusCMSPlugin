<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

/**
 * A <link rel="alternate" hreflang="…"> to the entry in another locale, or x-default to it in the channel's default locale
 */
final readonly class AlternateLink
{
    public function __construct(
        public string $hreflang,
        public string $url,
    ) {
    }
}
