<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

/**
 * An entry URL that another route matches in a channel and locale
 */
final readonly class TakenUrl
{
    /**
     * @param string|null $channelCode the channel it's matched in, or null when the entry is in no channel and it was
     *                                 matched on the current host
     * @param string $path the URL after the locale: "{prefix}/{slug}", or the slug alone for an empty prefix
     * @param string $route the name of the route that matches it
     */
    public function __construct(
        public ?string $channelCode,
        public string $localeCode,
        public string $path,
        public string $route,
    ) {
    }
}
