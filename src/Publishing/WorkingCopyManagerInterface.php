<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Reads and writes an entry's working copy: what the builder edits and previews. It's the entry's draft, or its rows
 * when it has none (ADR 0009). See "Drafts" in docs/spec/publishing.md
 */
interface WorkingCopyManagerInterface extends WorkingCopyProviderInterface
{
    /**
     * Saves a working copy got from getWorkingCopy(): to the rows of a never-published entry, which no shopper sees
     * yet, and otherwise to the entry's draft, which the first save after a publish creates. Saving a draft leaves changedAt alone and dispatches no event
     */
    public function save(EntryInterface $entry, EntryInterface $workingCopy): void;

    public function hasDraft(EntryInterface $entry): bool;

    /**
     * Deletes the draft, so the entry goes back to its published content
     */
    public function discard(EntryInterface $entry): void;

    /**
     * Copies the draft, when the entry has one, onto the rows and deletes it. It doesn't flush, because publishing
     * does that in its own transaction
     */
    public function applyDraft(EntryInterface $entry): void;
}
