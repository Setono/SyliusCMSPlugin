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

    /** A block preset with no blocks at all, so it has nothing to insert, while a preset is one or more saved blocks */
    case NoBlocks = 'no_blocks';

    /**
     * Whether setono:cms:preset:validate refuses a file for it
     */
    public function failsValidation(): bool
    {
        return self::DisabledBlockType !== $this;
    }

    /**
     * Whether a starting point with it can't be picked in the create dialog: its blocks of removed types would leave
     * holes in the new entry, and what's newer than the code can't be created from at all. With the others, the entry is
     * created and the problems flagged, or the default layout used. See "Presets with invalid blocks" in
     * docs/spec/layouts-and-presets.md
     */
    public function disablesStartingPoint(): bool
    {
        return self::RemovedBlockType === $this || self::NewerVersion === $this;
    }
}
