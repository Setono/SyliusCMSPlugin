<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * How complete the entries of one channel are in each of its locales, as the Overview's Translations shows it. See
 * "Overview" in docs/spec/admin.md
 */
final readonly class ChannelCompleteness
{
    /**
     * @param int $entries how many entries are in the channel
     * @param list<LocaleCompleteness> $locales the channel's locales, its default locale first
     */
    public function __construct(
        public string $channelCode,
        public string $channelName,
        public int $entries,
        public array $locales,
    ) {
    }
}
