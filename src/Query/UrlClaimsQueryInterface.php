<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Setono\SyliusCMSPlugin\Routing\UrlClaim;

/**
 * Finds the entries that have a URL in a locale, by the slug the URL holds for each content type. Uniqueness can't be a
 * database index, because an entry's channels are rows of their own, so the URL checks ask this.
 * See "Unique per channel and locale" in docs/spec/urls-and-seo.md
 */
interface UrlClaimsQueryInterface
{
    /**
     * The entries whose published content has the URL: the rows of published entries
     *
     * @param array<string, string> $slugs content type code => the slug the URL holds for it
     * @param string $exceptUuid the entry that's checked, which never clashes with itself
     *
     * @return list<UrlClaim>
     */
    public function published(string $localeCode, array $slugs, string $exceptUuid): array;

    /**
     * The entries whose working copy has the URL and isn't published: the rows of never-published entries, and the
     * drafts of published ones
     *
     * @param array<string, string> $slugs content type code => the slug the URL holds for it
     * @param string $exceptUuid the entry that's checked, which never clashes with itself
     *
     * @return list<UrlClaim>
     */
    public function drafted(string $localeCode, array $slugs, string $exceptUuid): array;
}
