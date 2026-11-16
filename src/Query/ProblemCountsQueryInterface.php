<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;

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
     * The entries' stored counts
     *
     * @param list<EntryInterface> $entries
     *
     * @return array<string, ProblemCount> by the entry's UUID, of the entries that are saved
     */
    public function ofEntries(array $entries): array;

    /**
     * The stored counts of every entry, added up
     */
    public function total(): ProblemCount;
}
