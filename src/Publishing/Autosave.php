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
 * wait for each other, and loading waits for a save in flight, so a lock token always matches the working copy it came with.
 * A save that changes something, and a discard, record on the entry who made it and when
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
        private EditRecorder $editRecorder,
    ) {
    }

    public function load(EntryInterface $entry): LoadedWorkingCopy
    {
        return $this->entryLock->read($entry, fn (): LoadedWorkingCopy => new LoadedWorkingCopy(
            $this->workingCopies->getWorkingCopy($entry),
            $this->savedChangeSets->findLatestLockToken($entry),
        ));
    }

    public function save(EntryInterface $entry, ChangeSet $changeSet, int $lockToken): SaveResult
    {
        return $this->entryLock->write($entry, function () use ($entry, $changeSet, $lockToken): SaveResult {
            $latest = $this->savedChangeSets->findLatestLockToken($entry);
            $theirs = $this->savedChangeSets->findChangeSetsSince($entry, $lockToken);
            if ($lockToken > $latest || count($theirs) !== $latest - $lockToken) {
                throw new StaleLockTokenException(sprintf('The lock token %d is not one the working copy at lock token %d can merge onto', $lockToken, $latest));
            }

            $workingCopy = $this->workingCopies->getWorkingCopy($entry);
            $merge = [] === $theirs ? new Merge($changeSet, []) : $this->merger->merge(
                new ChangeSet(array_merge(...array_map(static fn (ChangeSet $saved): array => $saved->changes, $theirs))),
                $changeSet,
                $workingCopy->getBlockTree(),
            );

            if ([] !== $merge->changeSet->changes) {
                $this->applier->apply($merge->changeSet, $workingCopy);

                $this->number($entry, ++$latest, $merge->changeSet);
                $this->editRecorder->record($entry);
                $this->workingCopies->save($entry, $workingCopy);
            }

            return new SaveResult(new LoadedWorkingCopy($workingCopy, $latest), [] !== $theirs, $merge->clashes);
        });
    }

    public function discard(EntryInterface $entry): LoadedWorkingCopy
    {
        return $this->entryLock->write($entry, function () use ($entry): LoadedWorkingCopy {
            $latest = $this->savedChangeSets->findLatestLockToken($entry);

            if ($this->workingCopies->hasDraft($entry)) {
                $this->number($entry, ++$latest, new ChangeSet([]));
                $this->editRecorder->record($entry);
                $this->workingCopies->discard($entry);
            }

            return new LoadedWorkingCopy($this->workingCopies->getWorkingCopy($entry), $latest);
        });
    }

    /**
     * Records a save under the lock token the working copy has after it, and forgets the saves too old to
     * merge onto. It's written on the next flush
     */
    private function number(EntryInterface $entry, int $lockToken, ChangeSet $changeSet): void
    {
        $now = $this->clock->now();
        $this->savedChangeSets->removeSavedBefore($entry, $now->modify('-' . self::KEPT_FOR));
        $this->savedChangeSets->record($entry, $lockToken, $changeSet, $now);
    }
}
