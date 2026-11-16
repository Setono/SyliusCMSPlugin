<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Keeps every slug an entry has had in each locale, so its URL redirects. See "Old slugs" in docs/spec/urls-and-seo.md
 */
interface OldSlugRecorderInterface
{
    /**
     * Records the slug of each locale whose slug a publish changed, for the next flush to write. A slug the entry has
     * again in a locale is its current one there, so it's no longer an old one
     *
     * @param array<string, string> $before locale => the slug shoppers saw before the publish
     * @param array<string, string> $after locale => the slug shoppers see after it
     */
    public function record(EntryInterface $entry, array $before, array $after): void;
}
