<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

final readonly class LoadedWorkingCopy
{
    /**
     * @param EntryInterface $workingCopy as WorkingCopyProviderInterface::getWorkingCopy() gives it
     * @param int $lockToken the number of saves of the working copy so far, which a save made on it carries
     */
    public function __construct(
        public EntryInterface $workingCopy,
        public int $lockToken,
    ) {
    }
}
