<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A warning or an error about a preset, which the Overview's Needs attention lists and the Content badge counts. Presets
 * aren't entries, so their problems are a kind of their own beside Problem. See "Problems" in docs/spec/admin.md
 */
final readonly class PresetProblem
{
    /**
     * @param string $kind the code of its kind, such as "preset_removed_block_type"
     * @param TranslatableInterface $message what's wrong, to translate where it's shown
     * @param Fix|null $fix where it's fixed, or null when that's in code, as for a code preset
     */
    public function __construct(
        public string $kind,
        public Severity $severity,
        public Preset $preset,
        public TranslatableInterface $message,
        public ?Fix $fix = null,
    ) {
    }
}
