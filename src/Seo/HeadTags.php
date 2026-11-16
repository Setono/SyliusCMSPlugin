<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

/**
 * What an entry shown at its own URL prints into the shop layout's head. No Open Graph tags, so they never appear twice
 * beside an SEO plugin's. See "Head tags" in docs/spec/urls-and-seo.md
 */
final readonly class HeadTags
{
    /**
     * @param string|null $title the meta title, or the title. Null when neither has a value
     * @param string|null $description the meta description, left out when null
     * @param bool $noindex whether crawlers are asked not to index the page: "Hide from search engines"
     * @param string|null $canonicalUrl the entry's absolute URL in the channel and locale, without a query string
     * @param list<AlternateLink> $alternates a hreflang link per locale the entry shows in, by locale code, then x-default
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public bool $noindex,
        public ?string $canonicalUrl,
        public array $alternates,
    ) {
    }
}
