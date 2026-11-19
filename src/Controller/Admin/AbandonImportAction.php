<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Transfer\Import\ImportQueueInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The import page's Cancel: abandons an import before it's confirmed and deletes its zip from the media storage, then
 * goes back to the Transfer screen. See "Transfer" in docs/spec/admin.md
 */
final readonly class AbandonImportAction
{
    public function __construct(
        private ImportScreenRequest $importRequest,
        private ImportQueueInterface $queue,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $import = $this->importRequest->import($uuid);
        $this->importRequest->checkCsrfToken($request);

        if (!in_array($import->getState(), [ImportState::Checking, ImportState::Checked], true)) {
            $this->importRequest->flash($request, 'error', 'setono_sylius_cms.import_not_waiting');

            return $this->importRequest->toImport($import);
        }

        $this->queue->abandon($import);
        $this->importRequest->flash($request, 'success', 'setono_sylius_cms.import_abandoned');

        return $this->importRequest->toTransfer();
    }
}
