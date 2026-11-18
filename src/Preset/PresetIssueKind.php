<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * What's wrong with a preset's blocks. See "Code presets" and "Presets with invalid blocks" in
 * docs/spec/layouts-and-presets.md
 */
enum PresetIssueKind: string
{
    /** A block of a type the code no longer has, which inserting leaves out, and the admin flags as a warning */
    case RemovedBlockType = 'removed_block_type';

    /** A block of a disabled type, which inserting leaves out. The type exists, so the file is valid */
    case DisabledBlockType = 'disabled_block_type';

    /** A block saved with a newer version than the code's, which makes inserting refuse */
    case NewerVersion = 'newer_version';

    /** A block where the nesting rules don't allow it, or too few or too many of a type */
    case Nesting = 'nesting';

    /** A value that doesn't fit its field type */
    case Misfit = 'misfit';

    /** A starting point whose content type or layout the code doesn't have */
    case StartingPoint = 'starting_point';

    /**
     * Whether setono:cms:preset:validate refuses a file for it
     */
    public function failsValidation(): bool
    {
        return self::DisabledBlockType !== $this;
    }
}
