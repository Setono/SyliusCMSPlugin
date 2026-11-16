<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Finds the URLs of an entry that a route other than the entry route matches, in the entry's channels and locales.
 * See "Reserved URLs" and "Routes added later" in docs/spec/urls-and-seo.md
 */
interface TakenUrlFinderInterface
{
    /**
     * @param array<string, string> $slugs locale => the slug whose URL is checked
     * @param list<string> $channelCodes the channels the URLs are checked in. With none, they're checked once, on the
     *                                   current host
     * @param bool $shownOnly whether to check a URL only in the channels that have its locale, where it shows
     *
     * @return list<TakenUrl> by channel, then by locale as the slugs are given
     */
    public function find(EntryInterface $entry, array $slugs, array $channelCodes, bool $shownOnly = false): array;
}
