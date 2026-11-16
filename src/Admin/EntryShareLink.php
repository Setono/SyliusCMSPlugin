<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;

/**
 * A share link as the Share links section of its entry's overview page lists it
 */
final readonly class EntryShareLink
{
    /**
     * @param string|null $url the link to copy, or null when the entry's draft no longer has a URL in its locale
     * @param string $channelName the name of its channel, or the channel's code when it has no name or is gone
     */
    public function __construct(
        public ShareLinkInterface $shareLink,
        public ?string $url,
        public string $channelName,
    ) {
    }
}
