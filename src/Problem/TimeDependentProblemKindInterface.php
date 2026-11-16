<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Doctrine\ORM\QueryBuilder;

/**
 * A problem kind whose problems come with time, with nothing written, such as a scheduled publish that's overdue once
 * its time has passed. Its problems can't be counted when an entry is written, so the lists' problem marker, their
 * Needs attention filter and the Content badge find them with its query condition, as of now, and count one for each
 * entry that has any. The overview page and setono:cms:lint find them with findIn(), as any kind's. It's optional:
 * a kind that doesn't implement it is counted when an entry is written. See "Problems" in docs/spec/admin.md
 */
interface TimeDependentProblemKindInterface extends ProblemKindInterface
{
    /**
     * How bad its problems are
     */
    public function getSeverity(): Severity;

    /**
     * The DQL condition that holds for an entry with a problem of this kind at $now. It sets the parameters it needs
     * on the query builder. The names of its parameters and the aliases in it start with "cms_problem_" and a name of
     * the kind's own, so they're unique in the query
     *
     * @param string $entry the alias of the entries in the query
     */
    public function getCondition(QueryBuilder $queryBuilder, string $entry, \DateTimeImmutable $now): string;
}
