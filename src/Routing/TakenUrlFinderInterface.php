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
     * The URLs of these slugs another route takes, in every channel, in every locale a slug is given in
     *
     * @param array<string, string> $slugs locale => the slug whose URL is checked
     * @param list<string> $channelCodes the channels the URLs are checked in. With none, they're checked once, on the
     *                                   current host
     *
     * @return list<TakenUrl> by channel, then by locale as the slugs are given
     */
    public function find(EntryInterface $entry, array $slugs, array $channelCodes): array;

    /**
     * The entry's live URLs another route takes: its published slugs, in its channels, in the locales it shows in that
     * the channel has. A never-published entry, and one in no channel, has none. setono:cms:check-routes, the "URL taken
     * by a Sylius route" problem and the overview page's Where it shows all read this
     *
     * @param EntryInterface $entry the entry's rows, which hold its published content
     *
     * @return list<TakenUrl> by channel, then by locale
     */
    public function findLive(EntryInterface $entry): array;
}
