<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The checks of an entry's URLs, for a content type with its own URL: its slugs, other routes and other entries' URLs.
 * Each problem points at the slug field in its locale. See "Slugs", "Unique per channel and locale" and "Reserved URLs"
 * in docs/spec/urls-and-seo.md
 */
interface EntryUrlValidatorInterface
{
    /**
     * What publish refuses: a slug that's empty or breaks the slug rules, a URL another route matches in the entry's
     * channels and locales, and a URL another entry has published in a channel and locale they share
     *
     * @param EntryInterface $entry the entry as it would be published: its working copy
     *
     * @return list<ValidationError>
     */
    public function validate(EntryInterface $entry): array;

    /**
     * What the builder warns of on save, because a draft saves even when it's invalid: URLs other entries have drafted
     * and not published, in a channel and locale they share. Other entries' published URLs are what validate() refuses
     *
     * @param EntryInterface $entry the working copy
     *
     * @return list<ValidationError>
     */
    public function warnOfDraftedUrls(EntryInterface $entry): array;

    /**
     * The URLs a published entry shows at that another route matches now, such as a route added after it was published.
     * Shoppers get the route's page there
     *
     * @param EntryInterface $entry the entry's rows, which hold its published content
     *
     * @return list<ValidationError>
     */
    public function warnOfLiveUrlsTakenByRoutes(EntryInterface $entry): array;

    /**
     * The slugs whose URL another route matches, which a save refuses, the one exception to a draft saving while invalid
     *
     * @param EntryInterface $entry the working copy, whose channels the URLs are matched in
     * @param array<string, string> $slugs locale => the slug the save would give it
     *
     * @return array<string, ValidationError> by locale, for the slugs refused
     */
    public function refuseUrlsTakenByRoutes(EntryInterface $entry, array $slugs): array;

    /**
     * The published URLs of the entry that another entry has published in a channel the entry would be added to,
     * which makes a change of its channels refused on save
     *
     * @param EntryInterface $entry the entry's rows
     * @param list<string> $channelCodes the channels the entry would be in
     *
     * @return list<ValidationError>
     */
    public function validateChannels(EntryInterface $entry, array $channelCodes): array;
}
