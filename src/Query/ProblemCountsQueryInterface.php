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
     */
    public function store(array $counts, string $fingerprint): void;

    /**
     * Has $count count up to $limit entries again whose counts were made with another fingerprint, or never, in the
     * order of their ids, and stores what it counts. It claims them first, so two requests never count the same
     * entries, and a claim lapses after a few minutes, in case a request stops before it stores. A write that counts
     * one of them meanwhile wins. Only entries of the content types the code has are counted: one whose content type
     * was removed can't be loaded. The entries it loads are detached once they're counted, except those the request
     * had loaded already
     *
     * @param callable(list<EntryInterface>): array<int, ProblemCount> $count the counts of the entries, by id. An entry
     *                                                                       it leaves out keeps its counts, and stays stale
     */
    public function countStale(string $fingerprint, int $limit, callable $count): void;

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
     * @param list<string> $hiddenContentTypes the codes of the content types switched off, whose entries are left out
     */
    public function total(iterable $kinds, \DateTimeImmutable $now, array $hiddenContentTypes = []): ProblemCount;
}
