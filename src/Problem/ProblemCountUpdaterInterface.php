<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Counts the errors and warnings of entries and stores the counts with them, where the lists' problem marker, their
 * Needs attention filter and the Content badge read them (ProblemCountProviderInterface), so no admin page goes
 * through every entry. ProblemCountListener has the entries a write touches counted in the same request. A project
 * whose own problem kind depends on something else that changes calls update() with the entries it changes.
 * See "Problems" in docs/spec/admin.md
 */
interface ProblemCountUpdaterInterface
{
    /**
     * Counts the problems of each entry's working copy, of every kind but the time-dependent ones, which are found
     * when the counts are read, and stores the counts
     *
     * @param iterable<EntryInterface> $entries
     */
    public function update(iterable $entries): void;

    /**
     * Counts the entries again whose counts were made with other code, such as before a deploy that removed a block
     * type, and those never counted: a batch of them, which no other request counts at the same time, so an admin page
     * that reads the counts stays quick, and the requests after it count the rest
     */
    public function updateStale(): void;
}
