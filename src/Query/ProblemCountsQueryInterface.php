<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\TimeDependentProblemKindInterface;

/**
 * Reads and writes the problem counts stored with the entries, in SQL, so no admin page goes through every entry.
 * See "Problems" in docs/spec/admin.md
 */
interface ProblemCountsQueryInterface
{
    /**
     * Stores the entries' counts, made with the fingerprint
     *
     * @param array<int, ProblemCount> $counts by the entry's id
     * @param bool $onlyStale whether to leave an entry whose counts were made with the fingerprint since, so counting
     *                        stale entries never overwrites what a write counted meanwhile
     */
    public function store(array $counts, string $fingerprint, bool $onlyStale = false): void;

    /**
     * The entries' stored counts, with one error or warning for each time-dependent kind an entry has a problem of
     * at $now
     *
     * @param list<EntryInterface> $entries
     * @param iterable<TimeDependentProblemKindInterface> $kinds
     *
     * @return array<string, ProblemCount> by the entry's UUID, of the entries that are saved
     */
    public function ofEntries(array $entries, iterable $kinds, \DateTimeImmutable $now): array;

    /**
     * The stored counts of every entry added up, with one error or warning for each entry with a problem of each
     * time-dependent kind at $now
     *
     * @param iterable<TimeDependentProblemKindInterface> $kinds
     */
    public function total(iterable $kinds, \DateTimeImmutable $now): ProblemCount;
}
