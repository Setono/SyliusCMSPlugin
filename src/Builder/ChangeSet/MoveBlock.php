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
     * @param int $index its position in that list once it's taken out of where it was, where it goes when the list
     *                   doesn't have the block $after
     * @param string|null $after the key of the block it goes right after, wherever that block is in the list by now,
     *                           so that a merged save puts it where the editor put it. Null to go by $index alone
     */
    public function __construct(
        public string $key,
        public ?string $parent,
        public int $index,
        public ?string $after = null,
    ) {
    }
}
