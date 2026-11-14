<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Allows a block type in a spot of the tree, with an optional min and max
 */
final readonly class Allow
{
    /**
     * @param string $type a block type class, or "*" for any block type whose "parents" allows it
     */
    public function __construct(
        public string $type,
        public ?int $min = null,
        public ?int $max = null,
    ) {
    }
}
