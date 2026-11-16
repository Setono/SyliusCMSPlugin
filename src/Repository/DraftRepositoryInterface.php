<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

interface DraftRepositoryInterface
{
    public function findOneByEntry(EntryInterface $entry): ?DraftInterface;

    /**
     * The drafts of these entries, in one query, for the entries that have one
     *
     * @param list<EntryInterface> $entries
     *
     * @return list<DraftInterface>
     */
    public function findByEntries(array $entries): array;
}
