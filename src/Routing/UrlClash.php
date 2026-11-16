<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

/**
 * One of an entry's URLs that another entry has too, in a locale and channels they share
 */
final readonly class UrlClash
{
    /**
     * @param string $path the URL after the locale: "{prefix}/{slug}", or the slug alone for an empty prefix
     * @param UrlClaim $other the other entry, with its own slug, which the URL holds with its content type's prefix
     * @param list<string> $channelCodes the channels both entries are in
     */
    public function __construct(
        public string $localeCode,
        public string $path,
        public UrlClaim $other,
        public array $channelCodes,
    ) {
    }
}
