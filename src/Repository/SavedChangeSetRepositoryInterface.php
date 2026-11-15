<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The saves of entries' working copies, numbered by the version after each, which is the builder's lock token
 */
interface SavedChangeSetRepositoryInterface
{
    /**
     * The working copy's version: the number of the last save kept, or 0 before the first
     */
    public function findLatestVersion(EntryInterface $entry): int;

    /**
     * The change sets saved after the version, in order. A save that was deleted for its age leaves a gap
     *
     * @return list<ChangeSet>
     */
    public function findChangeSetsSince(EntryInterface $entry, int $version): array;

    /**
     * Records a save. It's written on the next flush
     */
    public function record(EntryInterface $entry, int $version, ChangeSet $changeSet, \DateTimeImmutable $savedAt): void;

    /**
     * Deletes the entry's saves made before the time
     */
    public function removeSavedBefore(EntryInterface $entry, \DateTimeImmutable $before): void;
}
