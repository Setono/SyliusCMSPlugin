<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * An entry's URL in a locale, as its overview page lists it
 */
final readonly class EntryUrl
{
    /**
     * @param string|null $url the path of the URL the locale has in the entry's rows, or null when the rows have no slug there
     * @param string|null $draftUrl the path of the URL the draft gives the locale once it's published, when that's another
     * @param list<string> $oldUrls the paths of the locale's old slugs, which redirect to its URL, newest first
     */
    public function __construct(
        public string $localeCode,
        public ?string $url,
        public ?string $draftUrl,
        public array $oldUrls = [],
    ) {
    }
}
