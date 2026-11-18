<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Tree\StoredLayout;

/**
 * What a preset remembers as a starting point for new entries of one content type: a layout, with its field values.
 * See "Starting points" in docs/spec/layouts-and-presets.md
 */
final readonly class StartingPoint
{
    /**
     * @param string $contentType the content type's code
     * @param StoredLayout|null $layout the layout as the tree stores it, or null for a content type without layouts
     * @param bool $default whether it's the content type's default starting point, which only a code preset can be
     */
    public function __construct(
        public string $contentType,
        public ?StoredLayout $layout,
        public bool $default = false,
    ) {
    }
}
