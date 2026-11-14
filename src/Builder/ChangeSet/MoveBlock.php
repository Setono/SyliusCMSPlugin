<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Moves a block, with everything in it, to a list of blocks: the top level or another block's children
 */
final readonly class MoveBlock implements Change
{
    /**
     * @param string|null $parent the key of the block whose children it joins, or null for the top level
     * @param int $index its position in that list once it's taken out of where it was
     */
    public function __construct(
        public string $key,
        public ?string $parent,
        public int $index,
    ) {
    }
}
