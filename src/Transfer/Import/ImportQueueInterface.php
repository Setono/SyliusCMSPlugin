<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * The imports started in the admin, which run on the Messenger worker: an uploaded zip is kept in the media storage and
 * gets its dry run, an admin user confirms its plan, and the worker imports it (RunImportHandler). One no one confirms
 * is abandoned, and its zip deleted. See "Transfer" in docs/spec/admin.md and "Who runs it" in docs/spec/transfer.md
 */
interface ImportQueueInterface
{
    /**
     * How long an uploaded zip waits for an admin user to confirm its import before it's abandoned, as a date interval
     */
    public const ABANDONED_AFTER = 'P1D';

    /**
     * Keeps the uploaded zip in the media storage, records the import, and has the worker run its dry run
     *
     * @param string $filename the name it was uploaded with
     *
     * @throws InvalidExportException when the file isn't a zip that holds an export's manifest
     */
    public function upload(\SplFileInfo $file, string $filename): ImportInterface;

    /**
     * Has the worker import it, with these options
     *
     * @param bool $overwrite whether to overwrite the records the plan lists as conflicts, discarding their drafts
     * @param bool $publish whether to publish every entry it writes
     *
     * @throws \LogicException unless its dry run would apply and it waits to be confirmed
     */
    public function confirm(ImportInterface $import, bool $overwrite, bool $publish): void;

    /**
     * Abandons an import no one confirmed, and deletes its zip
     *
     * @throws \LogicException once it's confirmed or done
     */
    public function abandon(ImportInterface $import): void;
}
