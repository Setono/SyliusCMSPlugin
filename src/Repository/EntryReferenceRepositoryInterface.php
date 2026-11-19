<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;

/**
 * Reads the reference table, which publishing writes through Collection\EntryReferenceIndexerInterface. Listings filter
 * on it with Collection\ReferenceTable::holding()
 */
interface EntryReferenceRepositoryInterface
{
    /**
     * The entry's rows: the UUIDs its indexed fields hold in its published content
     *
     * @return list<EntryReferenceInterface>
     */
    public function findByEntry(EntryInterface $entry): array;
}
