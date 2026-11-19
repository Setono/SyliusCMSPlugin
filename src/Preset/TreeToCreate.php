<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A new entry's block tree, built from a starting point, with what editors are told about it: the blocks of removed or
 * disabled types that were left out, or why it's refused. See "Starting points" in docs/spec/layouts-and-presets.md
 */
final readonly class TreeToCreate
{
    /**
     * @param StoredTree $tree the tree to create the entry with, without blocks when refused
     * @param list<TranslatableMessage> $notices
     * @param bool $refused whether a block or the layout was saved with a newer version than the code's, so the entry
     *                      can't be created from it
     */
    public function __construct(
        public StoredTree $tree,
        public array $notices = [],
        public bool $refused = false,
    ) {
    }
}
