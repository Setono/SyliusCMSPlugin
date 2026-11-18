<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Deletes an entry, with its translations, draft and on-save settings. See "Events" in docs/spec/publishing.md
 */
interface EntryDeleterInterface
{
    /**
     * Dispatches BeforeEntryDelete, deletes the entry, and dispatches EntryDeleted after the transaction commits, then
     * PlacementsChanged with the slots a deleted snippet was placed in, if any
     *
     * @throws DeleteVetoedException when a BeforeEntryDelete listener vetoes the delete
     */
    public function delete(EntryInterface $entry): void;
}
