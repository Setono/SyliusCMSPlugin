<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * Step 2 of an import: what it would do with what it read, without changing anything. See "What an import does" in
 * docs/spec/transfer.md
 */
interface ImportPlannerInterface
{
    public function plan(ReadExport $export): ImportPlan;
}
