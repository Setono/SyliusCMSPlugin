<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Media\FileSize;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportQueueInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Transfer screen's Upload: the export zip in `export` is kept in the plugin's media storage, and the Messenger
 * worker runs its dry run, which the import's page shows. A file that isn't an export zip, or that PHP didn't receive
 * in full, goes back to the screen with why. The screen's import-upload.js sends the form with fetch and asks for JSON,
 * `{url}`, the page to go to, so the Upload button shows it's uploading; without it the form posts and is redirected.
 * See "Transfer" in docs/spec/admin.md
 */
final readonly class UploadImportAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ImportScreenRequest $importRequest,
        private ImportQueueInterface $queue,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $to = $this->upload($request);

        return 'json' === $request->getPreferredFormat() ? new JsonResponse(['url' => $to->getTargetUrl()]) : $to;
    }

    private function upload(Request $request): RedirectResponse
    {
        // A request over PHP's post_max_size arrives without its file, and without its CSRF token, so that's said first
        $file = $request->files->get('export');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->importRequest->flash($request, 'error', 'setono_sylius_cms.import_not_uploaded', ['%limit%' => FileSize::format((int) UploadedFile::getMaxFilesize())]);

            return $this->importRequest->toTransfer();
        }

        $this->importRequest->checkCsrfToken($request);

        try {
            $import = $this->queue->upload($file, $file->getClientOriginalName());
        } catch (InvalidExportException) {
            $this->importRequest->flash($request, 'error', 'setono_sylius_cms.import_not_an_export', ['%filename%' => $file->getClientOriginalName()]);

            return $this->importRequest->toTransfer();
        }

        return $this->importRequest->toImport($import);
    }
}
