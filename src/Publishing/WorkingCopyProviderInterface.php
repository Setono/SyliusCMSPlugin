<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * An entry's working copy: what the builder edits and its preview renders. That's the entry's draft when it has one,
 * and otherwise its rows (docs/spec/publishing.md#drafts). Everything that reads the working copy goes through here
 */
interface WorkingCopyProviderInterface
{
    /**
     * The working copy as an entry, to read and render, never to persist: built from a draft, it isn't the entry's rows
     */
    public function getWorkingCopy(EntryInterface $entry): EntryInterface;
}
