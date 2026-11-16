<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * An entry's working copy: what the builder edits and its preview renders. That's the entry's draft when it has one,
 * and otherwise its rows (docs/spec/publishing.md#drafts). Everything that reads the working copy goes through here.
 * WorkingCopyManagerInterface adds saving it, discarding the draft and copying it onto the rows on publish
 */
interface WorkingCopyProviderInterface
{
    /**
     * The working copy as an entry, to read and render. A never-published entry is its own working copy. A published
     * entry's working copy is a copy that Doctrine doesn't manage, holding its draft, or its published content when it
     * has no draft, so changing it never changes what shoppers see. Changes are saved through WorkingCopyManagerInterface
     */
    public function getWorkingCopy(EntryInterface $entry): EntryInterface;
}
