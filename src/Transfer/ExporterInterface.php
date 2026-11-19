<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * Writes an export: the records an export plan holds, in the export format. Drafts, revisions, share links and old
 * slugs are never written. See "Export format" in docs/spec/transfer.md
 */
interface ExporterInterface
{
    public function export(ExportPlan $plan, ExportTargetInterface $target): void;
}
