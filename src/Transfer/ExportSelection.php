<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * What an operator picks to export, in the console command's options or on the Transfer screen. Picks combine. What the
 * picked records use comes along as dependencies (ExportPlannerInterface). See "Commands" in docs/spec/transfer.md
 */
final readonly class ExportSelection
{
    /**
     * @param list<string> $entries entry UUIDs
     * @param list<string> $contentTypes content type codes: every entry of each
     * @param list<string> $mediaFolders media folder UUIDs: every media item in each and in its subfolders
     * @param list<string> $presets editor preset UUIDs
     * @param bool $allPresets every editor preset
     * @param bool $everything every entry, editor preset and media item
     */
    public function __construct(
        public array $entries = [],
        public array $contentTypes = [],
        public array $mediaFolders = [],
        public array $presets = [],
        public bool $allPresets = false,
        public bool $everything = false,
    ) {
    }

    /**
     * Whether it picks nothing at all, which nothing exports
     */
    public function isEmpty(): bool
    {
        return [] === $this->entries && [] === $this->contentTypes && [] === $this->mediaFolders && [] === $this->presets && !$this->allPresets && !$this->everything;
    }
}
