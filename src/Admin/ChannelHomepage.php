<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\PageInterface;

/**
 * A channel and the page that's its homepage now, as a page's overview page lists them under "Homepage of"
 */
final readonly class ChannelHomepage
{
    /**
     * @param string $channelName the channel's name, or its code when it has none or was deleted
     * @param PageInterface|null $homepage the page that's the channel's homepage now, or null when Sylius's own shows
     * @param bool $ofThisPage whether that's the page the overview page shows
     */
    public function __construct(
        public string $channelCode,
        public string $channelName,
        public ?PageInterface $homepage,
        public bool $ofThisPage,
    ) {
    }
}
