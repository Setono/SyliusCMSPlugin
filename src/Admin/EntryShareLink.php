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
     * @param string|null $slotLabel for a snippet's link, the label of the slot of its placement, a translation key, or
     *                               the slot's identifier when the code no longer declares it. Null for a plain page
     */
    public function __construct(
        public ShareLinkInterface $shareLink,
        public ?string $url,
        public string $channelName,
        public ?string $slotLabel = null,
    ) {
    }
}
