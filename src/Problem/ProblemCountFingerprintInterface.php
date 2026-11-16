<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

/**
 * What the stored problem counts depend on in the code, as one string, which is stored with each entry's counts. A
 * deploy that changes it, such as one that removes a block type, has the entries counted again, once, on the next
 * admin page (ProblemCountUpdaterInterface::updateStale()). A project whose own problem kind reads other code
 * decorates it
 */
interface ProblemCountFingerprintInterface
{
    /**
     * @return non-empty-string at most 64 characters
     */
    public function get(): string;
}
