<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * Imports an export: it reads, checks and upgrades it, plans, and applies the plan all or nothing. A refusal is a
 * result, not an exception, so a caller on the Messenger worker records it rather than Messenger retrying it. Only an
 * unexpected error throws. See "What an import does" in docs/spec/transfer.md
 */
interface ImporterInterface
{
    public function import(ExportSourceInterface $source, ImportOptions $options): ImportResult;
}
