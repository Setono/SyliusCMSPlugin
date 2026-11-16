<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * No two entries have the same URL in one channel and locale. The URL is the content type's prefix plus the slug, so
 * entries of different content types can clash too, and entries with no channel in common never do.
 * See "Unique per channel and locale" in docs/spec/urls-and-seo.md
 */
interface UrlClashFinderInterface
{
    /**
     * The URLs of the entry that other entries have published, in a locale and a channel they share
     *
     * @param array<string, string> $slugs locale => the entry's slug there
     * @param list<string> $channelCodes the entry's channels
     *
     * @return list<UrlClash>
     */
    public function findPublished(EntryInterface $entry, array $slugs, array $channelCodes): array;

    /**
     * The URLs of the entry that other entries have drafted and not published: never-published entries' slugs, and the
     * slugs of published entries' drafts
     *
     * @param array<string, string> $slugs locale => the entry's slug there
     * @param list<string> $channelCodes the entry's channels
     *
     * @return list<UrlClash>
     */
    public function findDrafted(EntryInterface $entry, array $slugs, array $channelCodes): array;
}
