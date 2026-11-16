<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

/**
 * An entry that has a URL in a locale, published or drafted, and the channels it has it in
 */
final readonly class UrlClaim
{
    /**
     * @param string $slug the entry's slug in the locale, which is the URL with the entry's content type's prefix
     * @param string|null $title the entry's title in the locale, to name it by
     * @param list<string> $channelCodes the entry's channels
     */
    public function __construct(
        public string $uuid,
        public string $contentType,
        public string $localeCode,
        public string $slug,
        public ?string $title,
        public array $channelCodes,
    ) {
    }
}
