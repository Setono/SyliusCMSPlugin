<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

/**
 * An entry's block tree in the stored block tree format. See "The tree" in docs/spec/content-model.md
 */
final class StoredTree
{
    /**
     * @param list<StoredBlock> $blocks the top-level blocks, in order
     */
    public function __construct(
        public ?StoredLayout $layout,
        public array $blocks,
    ) {
    }

    /**
     * A clone is a deep copy, so changing it leaves the original as it is
     */
    public function __clone()
    {
        if (null !== $this->layout) {
            $this->layout = clone $this->layout;
        }

        $this->blocks = array_map(static fn (StoredBlock $block): StoredBlock => clone $block, $this->blocks);
    }

    public function findBlock(string $key): ?StoredBlock
    {
        return self::find($this->blocks, $key);
    }

    /**
     * Inserts a top-level block at $index, clamped to the list
     */
    public function insertBlock(StoredBlock $block, int $index): void
    {
        $index = max(0, min($index, count($this->blocks)));

        array_splice($this->blocks, $index, 0, [$block]);
    }

    /**
     * Removes the block with this key, wherever it is, together with its children
     *
     * @return bool whether a block was removed
     */
    public function removeBlock(string $key): bool
    {
        return self::remove($this->blocks, $key);
    }

    /**
     * @return list<string> every block key in the tree, depth first
     */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->blocks as $block) {
            $keys = [...$keys, ...$block->keys()];
        }

        return $keys;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function find(array $blocks, string $key): ?StoredBlock
    {
        foreach ($blocks as $block) {
            if ($block->key === $key) {
                return $block;
            }

            $found = self::find($block->children, $key);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function remove(array &$blocks, string $key): bool
    {
        foreach ($blocks as $index => $block) {
            if ($block->key === $key) {
                array_splice($blocks, $index, 1);

                return true;
            }

            if (self::remove($block->children, $key)) {
                return true;
            }
        }

        return false;
    }
}
