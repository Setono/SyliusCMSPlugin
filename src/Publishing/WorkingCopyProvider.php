<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Until drafts arrive (#217), saves write straight to the rows, so the working copy is the entry itself
 */
final class WorkingCopyProvider implements WorkingCopyProviderInterface
{
    public function getWorkingCopy(EntryInterface $entry): EntryInterface
    {
        return $entry;
    }
}
