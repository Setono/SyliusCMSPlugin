<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Writes an entry's rows of the reference table from what its indexed Entries fields hold on its rows, its published
 * content. A publish runs it as its step 5, and an import rebuilds the table through it. It persists and removes rows
 * without flushing, so they're written with the rest of the transaction. See "Categories" in docs/spec/collections.md
 */
interface EntryReferenceIndexerInterface
{
    public function index(EntryInterface $entry): void;
}
