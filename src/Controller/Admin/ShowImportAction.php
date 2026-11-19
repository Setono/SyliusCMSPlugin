<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResultNormalizer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The page of an import started on the Transfer screen. While the Messenger worker checks or imports it, it shows its
 * progress and looks again until that changes. Once the dry run would apply, it shows the plan: create, update,
 * unchanged or left alone per record, the conflicts, the homepage changes and the references that don't resolve, with
 * the overwrite checkbox, the option to publish everything imported, Import and Cancel. Once it's finished, it shows the
 * result: the imported entries, linked to review and publish them, or why it refused. See "Transfer" in
 * docs/spec/admin.md
 */
final readonly class ShowImportAction
{
    public function __construct(
        private ImportScreenRequest $importRequest,
        private ImportResultNormalizer $normalizer,
        private ContentTypeRegistryInterface $contentTypes,
        private Environment $twig,
    ) {
    }

    public function __invoke(string $uuid): Response
    {
        $import = $this->importRequest->import($uuid);
        $dryRun = $import->getDryRun();
        $result = $import->getResult();

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/transfer/import.html.twig', [
            'import' => $import,
            'dry_run' => null === $dryRun ? null : $this->normalizer->denormalize($dryRun),
            'result' => null === $result ? null : $this->normalizer->denormalize($result),
            'content_types' => array_combine(
                array_map(static fn (ContentTypeMetadata $contentType): string => $contentType->code, $this->contentTypes->all()),
                array_map(static fn (ContentTypeMetadata $contentType): string => $contentType->label, $this->contentTypes->all()),
            ),
            'csrf_token_id' => ImportScreenRequest::CSRF_TOKEN_ID,
        ]));
    }
}
