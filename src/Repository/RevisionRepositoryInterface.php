<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;

/**
 * The revisions of entries, which every publish stores. See "Revisions" in docs/spec/publishing.md
 */
interface RevisionRepositoryInterface
{
    public function findOneById(int $id): ?RevisionInterface;

    /**
     * The entry's revisions, newest first
     *
     * @return list<RevisionInterface>
     */
    public function findByEntry(EntryInterface $entry): array;

    /**
     * The ids of every revision with the id of its entry, grouped by entry and newest first within each, read as they're
     * iterated, for pruning
     *
     * @return iterable<array{entry: int, id: int}>
     */
    public function iterateIdsByEntryNewestFirst(): iterable;

    /**
     * Deletes the revisions with these ids
     *
     * @param list<int> $ids
     */
    public function removeByIds(array $ids): void;
}
