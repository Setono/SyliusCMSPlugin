<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\CodePresetExporter;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads an editor preset as a code preset file, <code>.json, the code being its name in snake case, with media
 * fields left empty: the same file setono:cms:preset:export writes. It's how developers compose code presets. It
 * changes nothing, so it's a link. See "Code presets" in docs/spec/layouts-and-presets.md
 */
final readonly class ExportPresetAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EditorPresetLookup $lookup,
        private CodePresetExporter $exporter,
    ) {
    }

    public function __invoke(string $uuid): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $file = $this->exporter->export($this->lookup->editorPreset($uuid));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->filename()),
        ]);
    }
}
