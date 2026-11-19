<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * How to import: setono:cms:import's options, and the admin's. See "Commands" in docs/spec/transfer.md
 */
final readonly class ImportOptions
{
    /**
     * @param bool $dryRun check everything the real run would and change nothing
     * @param bool $publish publish every entry the import writes, as part of the import
     * @param bool $overwrite overwrite the records the plan lists as conflicts, discarding their drafts and cancelling
     *                        their scheduled publishes. It never resolves a URL clash
     */
    public function __construct(
        public bool $dryRun = false,
        public bool $publish = false,
        public bool $overwrite = false,
    ) {
    }
}
