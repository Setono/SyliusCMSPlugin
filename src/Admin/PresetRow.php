<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Preset\Preset;

/**
 * A preset as the Presets screen lists it, its texts in the admin user's locale. See "Presets" in docs/spec/admin.md
 */
final readonly class PresetRow
{
    /**
     * @param string|null $contentType the label of the content type a starting point is for, or null for a block preset
     * @param list<string> $issues what's wrong with its blocks, which flags it
     */
    public function __construct(
        public Preset $preset,
        public string $name,
        public ?string $category,
        public ?string $description,
        public ?string $contentType,
        public array $issues,
    ) {
    }

    /**
     * A code preset's code or an editor preset's UUID
     */
    public function id(): string
    {
        return $this->preset->id();
    }

    /**
     * Whether it's a code preset, which is read-only
     */
    public function fromCode(): bool
    {
        return $this->preset->isFromCode();
    }
}
