<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Transfer\Import\ImportQueueInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The import page's Import: confirms an import whose dry run would apply, with its overwrite checkbox and its option to
 * publish everything imported, and has the Messenger worker run it. The page then shows its progress and its result.
 * See "Transfer" in docs/spec/admin.md
 */
final readonly class ConfirmImportAction
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

        if (ImportState::Checked !== $import->getState()) {
            $this->importRequest->flash($request, 'error', 'setono_sylius_cms.import_not_waiting');

            return $this->importRequest->toImport($import);
        }

        $this->queue->confirm($import, $request->request->getBoolean('overwrite'), $request->request->getBoolean('publish'));

        return $this->importRequest->toImport($import);
    }
}
