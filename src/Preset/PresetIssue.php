<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * One thing wrong with a preset, which setono:cms:preset:validate reports and the Presets tab marks the preset for
 */
final readonly class PresetIssue
{
    /**
     * @param string|null $block the key of the block it's in, as the preset has it, or null when it's about the preset
     */
    public function __construct(
        public PresetIssueKind $kind,
        public ?string $block,
        public TranslatableMessage $message,
    ) {
    }
}
