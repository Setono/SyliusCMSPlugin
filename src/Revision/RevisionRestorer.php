<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\LoadedWorkingCopy;

/**
 * Restores the revision's content, upgraded, through autosave, so it's saved like any change to the working copy and
 * builders that have the entry open pull it in. Autosave refuses it while a scheduled publish locks the draft
 */
final readonly class RevisionRestorer implements RevisionRestorerInterface
{
    public function __construct(
        private RevisionReader $reader,
        private AutosaveInterface $autosave,
    ) {
    }

    public function restore(RevisionInterface $revision): LoadedWorkingCopy
    {
        return $this->autosave->replace($revision->getEntry(), $this->reader->read($revision)->data);
    }
}
