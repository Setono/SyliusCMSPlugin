<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Which block is inside which, by key, kept in step with the additions and moves of a save as it's merged. Where a
 * block is in its list doesn't matter for telling what a change touches, so it isn't kept
 *
 * @internal
 */
final class Outline
{
    /** @var array<string, string|null> block key => its parent's key, or null at the top level */
    private array $parents = [];

    public function __construct(StoredTree $tree)
    {
        foreach ($tree->blocks as $block) {
            $this->add($block, null);
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parents);
    }

    /**
     * @return list<string> the block's key first, then the keys of every block inside it
     */
    public function keysWithin(string $key): array
    {
        $keys = [$key];
        foreach (array_keys($this->parents, $key, true) as $child) {
            $keys = [...$keys, ...$this->keysWithin($child)];
        }

        return $keys;
    }

    public function add(StoredBlock $block, ?string $parent): void
    {
        $this->parents[$block->key] = $parent;
        foreach ($block->children as $child) {
            $this->add($child, $block->key);
        }
    }

    /**
     * Moves a block into another, or to the top level, unless that's inside itself
     */
    public function move(string $key, ?string $parent): void
    {
        if (!in_array($parent, $this->keysWithin($key), true)) {
            $this->parents[$key] = $parent;
        }
    }
}
