<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetMerger;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Merge;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;

/**
 * Saves are kept for a day for merging onto. Each one runs with the entry's row locked (EntryLock), so saves of one entry
 * wait for each other, and loading waits for a save in flight, so a lock token always matches the working copy it came with
 */
final readonly class Autosave implements AutosaveInterface
{
    /**
     * How long saves are kept for merging onto. A builder that hasn't saved or pulled colleagues' changes in for longer
     * has to load the entry again
     */
    public const KEPT_FOR = '1 day';

    public function __construct(
        private EntryLock $entryLock,
        private WorkingCopyManagerInterface $workingCopies,
        private SavedChangeSetRepositoryInterface $savedChangeSets,
        private ChangeSetMerger $merger,
        private ChangeSetApplier $applier,
        private ClockInterface $clock,
    ) {
    }

    public function load(EntryInterface $entry): LoadedWorkingCopy
    {
        return $this->entryLock->read($entry, fn (): LoadedWorkingCopy => new LoadedWorkingCopy(
            $this->workingCopies->getWorkingCopy($entry),
            $this->savedChangeSets->findLatestVersion($entry),
        ));
    }

    public function save(EntryInterface $entry, ChangeSet $changeSet, int $lockToken): SaveResult
    {
        return $this->entryLock->write($entry, function () use ($entry, $changeSet, $lockToken): SaveResult {
            $version = $this->savedChangeSets->findLatestVersion($entry);
            $theirs = $this->savedChangeSets->findChangeSetsSince($entry, $lockToken);
            if ($lockToken > $version || count($theirs) !== $version - $lockToken) {
                throw new StaleLockTokenException(sprintf('The lock token %d is not one the working copy at version %d can merge onto', $lockToken, $version));
            }

            $workingCopy = $this->workingCopies->getWorkingCopy($entry);
            $merge = [] === $theirs ? new Merge($changeSet, []) : $this->merger->merge(
                new ChangeSet(array_merge(...array_map(static fn (ChangeSet $saved): array => $saved->changes, $theirs))),
                $changeSet,
                $workingCopy->getBlockTree(),
            );

            if ([] !== $merge->changeSet->changes) {
                $workingCopy->setBlockTree($this->applier->apply($merge->changeSet, $workingCopy->getBlockTree()));

                $this->number($entry, ++$version, $merge->changeSet);
                $this->workingCopies->save($entry, $workingCopy);
            }

            return new SaveResult(new LoadedWorkingCopy($workingCopy, $version), [] !== $theirs, $merge->clashes);
        });
    }

    public function discard(EntryInterface $entry): LoadedWorkingCopy
    {
        return $this->entryLock->write($entry, function () use ($entry): LoadedWorkingCopy {
            $version = $this->savedChangeSets->findLatestVersion($entry);

            if ($this->workingCopies->hasDraft($entry)) {
                $this->number($entry, ++$version, new ChangeSet([]));
                $this->workingCopies->discard($entry);
            }

            return new LoadedWorkingCopy($this->workingCopies->getWorkingCopy($entry), $version);
        });
    }

    /**
     * Records a save under its version, the lock token of the builder that made it, and forgets the saves too old to
     * merge onto. It's written on the next flush
     */
    private function number(EntryInterface $entry, int $version, ChangeSet $changeSet): void
    {
        $now = $this->clock->now();
        $this->savedChangeSets->removeSavedBefore($entry, $now->modify('-' . self::KEPT_FOR));
        $this->savedChangeSets->add($entry, $version, $changeSet, $now);
    }
}
