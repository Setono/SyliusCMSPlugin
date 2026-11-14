<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * A block style: a named look that editors pick for a block of one block type.
 * See "Block styles" in docs/spec/blocks.md
 */
final readonly class Style
{
    /**
     * @param string $code stored in the block's "style"; matches ^[a-z][a-z0-9_]*$ and never changes
     * @param string $label a translation key, shown in the builder's style picker
     * @param string $class the CSS class added to the block's root element
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $class,
    ) {
    }
}
