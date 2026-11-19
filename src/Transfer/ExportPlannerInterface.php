<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * Works out what an export of a selection holds: the picked entries, editor presets and media items, plus the snippets
 * they reference and the media items they use, including a video's poster, as dependencies. Entries that are only
 * linked to aren't pulled in. See "What a transfer moves" in docs/spec/transfer.md
 */
interface ExportPlannerInterface
{
    /**
     * @throws InvalidExportSelectionException when the selection names a record or a content type there's none of
     */
    public function plan(ExportSelection $selection): ExportPlan;
}
