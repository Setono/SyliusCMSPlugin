<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Merges a save onto what colleagues saved since the builder last loaded. Changes that don't touch the same values are
 * both applied, and so are moves. A real clash goes to the editor instead of being applied: the same field in the same
 * locale, an edit to a block a colleague deleted, or deleting a block a colleague changed something in.
 * See "Saving" in docs/spec/publishing.md
 */
final readonly class ChangeSetMerger
{
    /**
     * @param ChangeSet $theirs what colleagues saved since the builder's lock token, in order
     * @param ChangeSet $mine the editor's changes, made on the working copy as it was at the builder's lock token
     * @param StoredTree $tree the working copy's tree as it is now, with $theirs applied
     */
    public function merge(ChangeSet $theirs, ChangeSet $mine, StoredTree $tree): Merge
    {
        $footprint = new Footprint($theirs);

        // The blocks as they are once the changes applied so far are, to tell which blocks a change finds
        $working = clone $tree;
        // Blocks that came with a change that isn't applied, so the changes to them aren't either
        $withheld = [];

        $changes = [];
        $clashes = [];
        $clash = static function (Clash $clash) use (&$clashes): void {
            $clashes[CanonicalJson::encode($clash->toArray())] = $clash;
        };

        foreach ($mine->changes as $change) {
            $missing = self::missingBlock($change, $working);
            if (null !== $missing) {
                if (!isset($withheld[$missing]) && !$change instanceof RemoveBlock) {
                    $clash(BlockClash::deleted($missing));
                }

                if ($change instanceof AddBlock) {
                    $withheld += array_fill_keys($change->block->keys(), true);
                }

                continue;
            }

            if ($change instanceof SetValue && $footprint->hasValue($change)) {
                $current = self::currentValue($working, $change);
                if (CanonicalJson::encode($current) !== CanonicalJson::encode($change->value)) {
                    $clash(new ValueClash($change->key, $change->field, $change->locale, $change->value, $current));

                    continue;
                }
            }

            if ($change instanceof RemoveBlock && $footprint->changedWithin($working->findBlock($change->key)?->keys() ?? [])) {
                $clash(BlockClash::changed($change->key));

                continue;
            }

            self::track($change, $working);
            $changes[] = $change;
        }

        return new Merge(new ChangeSet($changes), array_values($clashes));
    }

    /**
     * The key of a block the change needs that the working copy doesn't have, or null
     */
    private static function missingBlock(Change $change, StoredTree $working): ?string
    {
        $needed = match (true) {
            $change instanceof SetValue, $change instanceof RemoveBlock => [$change->key],
            $change instanceof MoveBlock => [$change->key, $change->parent],
            $change instanceof AddBlock => [$change->parent],
            default => [],
        };

        foreach ($needed as $key) {
            if (null !== $key && null === $working->findBlock($key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Keeps the working copy's blocks in step with an applied change. Where a block lands in its list doesn't matter here
     */
    private static function track(Change $change, StoredTree $working): void
    {
        if ($change instanceof AddBlock) {
            $working->insertBlock(clone $change->block, 0, $change->parent);
        } elseif ($change instanceof MoveBlock) {
            $block = $working->findBlock($change->key);
            if (null !== $block && (null === $change->parent || !in_array($change->parent, $block->keys(), true))) {
                $working->removeBlock($change->key);
                $working->insertBlock($block, 0, $change->parent);
            }
        } elseif ($change instanceof RemoveBlock) {
            $working->removeBlock($change->key);
        }
    }

    private static function currentValue(StoredTree $tree, SetValue $change): mixed
    {
        $value = $tree->findBlock($change->key)?->fields[$change->field] ?? null;

        return null === $change->locale || !is_array($value) ? $value : $value[$change->locale] ?? null;
    }
}
