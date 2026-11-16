<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The server's end of the builder's autosave. Several editors save to one working copy, so every save is numbered:
 * the number is the lock token of the builder that made it. A save with an older lock token is merged onto what
 * colleagues saved since. See "Saving" in docs/spec/publishing.md
 */
interface AutosaveInterface
{
    /**
     * The working copy, with the lock token that a save made on it carries
     */
    public function load(EntryInterface $entry): LoadedWorkingCopy;

    /**
     * Applies the builder's changes to the working copy, merged onto what colleagues saved since the lock token
     *
     * @param int $lockToken what the builder got when it last loaded, saved or pulled colleagues' changes in
     *
     * @throws InvalidChangeSetException when the changes don't apply, and nothing is saved
     * @throws StaleLockTokenException when the lock token is one the server can't merge onto, and nothing is saved
     */
    public function save(EntryInterface $entry, ChangeSet $changeSet, int $lockToken): SaveResult;

    /**
     * Discards the draft, so the entry goes back to its published content. The discard is numbered like a save with no
     * changes, so builders still on the draft pull the published content in on their next presence ping or save, and a
     * save made on the draft merges onto the published content: an edit to a block only the draft had is a clash
     *
     * @return LoadedWorkingCopy the working copy, which is the published content, with its lock token
     */
    public function discard(EntryInterface $entry): LoadedWorkingCopy;
}
