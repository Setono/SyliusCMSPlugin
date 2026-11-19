<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * A channel whose homepage the import changes: an imported page becomes it, taking it off the page that was, or an
 * imported page stops being it, so the channel falls back to Sylius's own. See "Homepage" in
 * docs/spec/urls-and-seo.md
 */
final readonly class HomepageChange
{
    /**
     * @param string|null $from the UUID of the page that's the channel's homepage now, or null for none
     * @param string|null $fromTitle its title
     * @param string|null $to the UUID of the page that will be, or null for none
     * @param string|null $toTitle its title
     */
    public function __construct(
        public string $channelCode,
        public ?string $from,
        public ?string $fromTitle,
        public ?string $to,
        public ?string $toTitle,
    ) {
    }
}
