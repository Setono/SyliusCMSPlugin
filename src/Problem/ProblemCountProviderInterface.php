<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The errors and warnings that need attention, which the lists' problem marker, their Needs attention filter and the
 * Content badge show. They're read from the counts stored with the entries (ProblemCountUpdaterInterface), so no
 * admin page goes through every entry. The overview page finds an entry's problems as they are now
 * (ProblemFinderInterface). See "Problems" in docs/spec/admin.md
 */
interface ProblemCountProviderInterface
{
    /**
     * The errors and warnings across the store, which the Content badge counts
     */
    public function getTotal(): ProblemCount;

    /**
     * Each entry's errors and warnings
     *
     * @param iterable<EntryInterface> $entries
     *
     * @return array<string, ProblemCount> by the entry's UUID, of every entry given
     */
    public function getCounts(iterable $entries): array;

    /**
     * The DQL condition that holds for the entries that need attention, those with an error or a warning, which the
     * Needs attention filter adds to the lists' query, so the list pages as any other. It sets the parameters it needs
     * on the query builder
     *
     * @param string $entry the alias of the entries in the query
     */
    public function getNeedsAttentionCondition(QueryBuilder $queryBuilder, string $entry): string;
}
