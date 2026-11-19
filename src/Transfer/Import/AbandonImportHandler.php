<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;

/**
 * Abandons an import started in the admin that no one confirmed in time, deleting its zip. One that was confirmed, has
 * finished or was abandoned already is left as it is. See "Who runs it" in docs/spec/transfer.md
 */
final readonly class AbandonImportHandler
{
    public function __construct(
        private ImportRepositoryInterface $imports,
        private ImportQueueInterface $queue,
    ) {
    }

    public function __invoke(AbandonImport $message): void
    {
        $import = $this->imports->findOneByUuid($message->importUuid);
        if (null === $import || !in_array($import->getState(), [ImportState::Checking, ImportState::Checked], true)) {
            return;
        }

        $this->queue->abandon($import);
    }
}
