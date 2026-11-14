<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Reads and writes an entry's working copy: what the builder edits and previews. It's the entry's draft, or its rows
 * when it has none (ADR 0009). See "Drafts" in docs/spec/publishing.md
 */
interface WorkingCopyManagerInterface
{
    /**
     * A never-published entry is its own working copy, so saving it writes the rows, which no shopper sees yet.
     *
     * A published entry's working copy is a copy of it that Doctrine doesn't manage, holding its draft, or its published
     * content when it has no draft yet. Changing it never changes what shoppers see
     */
    public function get(EntryInterface $entry): EntryInterface;

    /**
     * Saves a working copy got from get(): to the rows of a never-published entry, and otherwise to the entry's draft,
     * which the first save after a publish creates. Saving a draft leaves changedAt alone and dispatches no event
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
