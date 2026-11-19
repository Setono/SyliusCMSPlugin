<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Reads and writes the locales each entry's working copy is complete in, which are stored with the entry, so the
 * Overview's Translations and the lists' translation filter go through no entry. An entry not counted yet is complete
 * in no locale. See "Overview" in docs/spec/admin.md and "Completeness and outdated flags" in docs/spec/localization.md
 */
interface CompletenessQueryInterface
{
    /**
     * Stores the locales each entry is complete in, in place of what it had
     *
     * @param array<int, list<string>> $completeLocales by the entry's id
     */
    public function store(array $completeLocales): void;

    /**
     * Per channel, how many entries are in it, and how many of them are complete in each of its locales
     *
     * @param array<string, list<string>> $localesByChannel the locale codes of each channel, by channel code
     * @param list<string> $hiddenContentTypes the codes of the content types switched off, whose entries are left out
     *
     * @return array<string, array{entries: int, complete: array<string, int>}> by channel code, in the order given, with
     *                                                                          the locales in the order given
     */
    public function count(array $localesByChannel, array $hiddenContentTypes = []): array;

    /**
     * The DQL condition that holds for the entries that aren't complete in the locale. It sets the parameter it needs on
     * the query builder
     *
     * @param string $entry the alias of the entries in the query
     */
    public function getIncompleteCondition(QueryBuilder $queryBuilder, string $entry, string $localeCode): string;
}
