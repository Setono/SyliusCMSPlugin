<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * A channel and a locale that Share draft on an entry's overview page offers to share the entry's draft in
 */
final readonly class ShareLinkTarget
{
    /**
     * @param string $channelName the channel's name, or its code when it has none
     */
    public function __construct(
        public string $channelCode,
        public string $channelName,
        public string $localeCode,
    ) {
    }
}
