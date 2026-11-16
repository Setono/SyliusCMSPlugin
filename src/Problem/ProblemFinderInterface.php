<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Finds an entry's problems, of every kind, in what the admin shows of it: its working copy
 */
interface ProblemFinderInterface
{
    /**
     * @return list<Problem> errors first, then warnings, each in the order the kinds find them
     */
    public function findByEntry(EntryInterface $entry): array;
}
