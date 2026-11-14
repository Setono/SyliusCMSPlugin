<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * A block type a spot in the tree may hold, with the fewest and the most blocks of it there
 */
final readonly class AllowedBlockType
{
    public function __construct(
        public string $code,
        public ?int $min,
        public ?int $max,
    ) {
    }
}
