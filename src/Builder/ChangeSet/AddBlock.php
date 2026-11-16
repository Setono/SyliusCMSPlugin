<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

final readonly class AddBlock implements Change
{
    /**
     * @param string|null $parent the parent block's key, or null for the top level
     * @param int $index its position in that list, where it goes when the list doesn't have the block $after
     * @param string|null $after the key of the block it goes right after, wherever that block is in the list by now,
     *                           so that a merged save puts it where the editor put it. Null to go by $index alone
     */
    public function __construct(
        public ?string $parent,
        public int $index,
        public StoredBlock $block,
        public ?string $after = null,
    ) {
    }
}
