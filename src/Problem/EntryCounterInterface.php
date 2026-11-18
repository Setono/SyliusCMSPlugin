<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Something else counted from each entry's working copy and stored with the entry, such as the locales it's complete
 * in. ProblemCountUpdater hands it the working copies it reads to count the problems, so it's counted again on the
 * same writes and deploys, and a working copy is read once for all of them
 *
 * @internal
 */
interface EntryCounterInterface
{
    /**
     * Counts and stores what it counts of each entry
     *
     * @param array<int, EntryInterface> $workingCopies the working copies of the entries, by the entry's id
     */
    public function count(array $workingCopies): void;
}
