<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

final readonly class AddBlock implements Change
{
    /**
     * @param string|null $parent the parent block's key, or null for the top level
     */
    public function __construct(
        public ?string $parent,
        public int $index,
        public StoredBlock $block,
    ) {
    }
}
