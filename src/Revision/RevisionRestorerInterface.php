<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Publishing\LoadedWorkingCopy;

/**
 * Restores a revision: copies it into the entry's draft, replacing what the draft held, never straight to live. The
 * editor reviews it and then publishes. See "Revisions" in docs/spec/publishing.md
 */
interface RevisionRestorerInterface
{
    /**
     * @return LoadedWorkingCopy the working copy, which holds the revision's content, with its lock token
     *
     * @throws InvalidEntryFormatException when the revision's content isn't its entry in the entry format
     */
    public function restore(RevisionInterface $revision): LoadedWorkingCopy;
}
