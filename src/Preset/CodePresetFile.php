<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * A code preset file: its code, which names it as <code>.json, and what's in it
 */
final readonly class CodePresetFile
{
    /**
     * @param string $code the file's name without .json, which matches ^[a-z][a-z0-9_]*$
     * @param string $contents the preset in the preset file format, as canonical JSON
     */
    public function __construct(
        public string $code,
        public string $contents,
    ) {
    }

    public function filename(): string
    {
        return $this->code . '.json';
    }
}
