<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

/**
 * What the builder's search endpoint searches for one kind of thing pickers pick, such as customer groups. A search
 * is a service tagged setono_sylius_cms.search with its kind. The endpoint is internal to the builder, so this is too.
 * See "JSON endpoints" in docs/spec/builder.md
 *
 * @internal
 */
interface SearchInterface
{
    /**
     * What matches the query, best first, at most a page of it
     *
     * @param string $query what the editor typed, which may be empty
     * @param string $localeCode the admin user's locale, to name things in
     *
     * @return list<SearchResult>
     */
    public function search(string $query, string $localeCode): array;
}
