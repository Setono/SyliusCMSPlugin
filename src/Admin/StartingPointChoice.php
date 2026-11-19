<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Preset\Preset;

/**
 * A starting point as the create dialog offers it, its texts in the admin user's locale. See "Starting points" in
 * docs/spec/layouts-and-presets.md and "Create dialog" in docs/spec/admin.md
 */
final readonly class StartingPointChoice
{
    /**
     * @param string|null $thumbnail a code preset's thumbnail, a public path to an image, or null
     * @param bool $default whether it's the content type's default starting point, which the dialog preselects
     * @param list<string> $issues what's wrong with its blocks or its layout, which the dialog says
     * @param bool $disabled whether it can't be picked, as it has blocks of types the code no longer has, or was saved
     *                       with a newer version than the code's
     */
    public function __construct(
        public Preset $preset,
        public string $name,
        public ?string $description,
        public ?string $thumbnail,
        public bool $default,
        public array $issues,
        public bool $disabled = false,
    ) {
    }

    /**
     * A code preset's code or an editor preset's UUID
     */
    public function id(): string
    {
        return $this->preset->id();
    }
}
