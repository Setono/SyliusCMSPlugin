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
        // Which blocks there are once the changes applied so far are
        $outline = new Outline($tree);
        // Blocks that came with an addition that isn't applied, so the changes to them aren't either
        $withheld = [];

        $changes = [];
        $clashes = [];
        foreach ($mine->changes as $change) {
            $missing = self::missingBlock($change, $outline);
            if (null !== $missing) {
                // Removing a block a colleague deleted too leaves nothing to clash over
                if (!$change instanceof RemoveBlock && !in_array($missing, $withheld, true)) {
                    $clashes[] = BlockClash::deleted($missing);
                }

                if ($change instanceof AddBlock) {
                    $withheld = [...$withheld, ...$change->block->keys()];
                }

                continue;
            }

            $clash = self::clash($change, $footprint, $outline);
            if (null !== $clash) {
                $clashes[] = $clash;

                continue;
            }

            if ($change instanceof AddBlock) {
                $outline->add($change->block, $change->parent);
            }

            if ($change instanceof MoveBlock) {
                $outline->move($change->key, $change->parent);
            }

            $changes[] = $change;
        }

        // Several changes to one deleted block make one clash
        $unique = [];
        foreach ($clashes as $clash) {
            $unique[CanonicalJson::encode($clash->toArray())] = $clash;
        }

        return new Merge(new ChangeSet($changes), array_values($unique));
    }

    /**
     * The key of a block the change needs that isn't there, or null
     */
    private static function missingBlock(Change $change, Outline $outline): ?string
    {
        $needed = match (true) {
            $change instanceof SetValue, $change instanceof RemoveBlock => [$change->key],
            $change instanceof MoveBlock => [$change->key, $change->parent],
            $change instanceof AddBlock => [$change->parent],
            // A change the applier doesn't know, which it refuses
            default => [],
        };

        foreach ($needed as $key) {
            if (null !== $key && !$outline->has($key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The clash over a value a colleague set to something else, or over removing a block they changed something in
     */
    private static function clash(Change $change, Footprint $footprint, Outline $outline): ?Clash
    {
        if ($change instanceof SetValue && $footprint->hasValue($change)) {
            $theirs = $footprint->valueOf($change);

            return CanonicalJson::encode($theirs) === CanonicalJson::encode($change->value)
                ? null
                : new ValueClash($change->key, $change->field, $change->locale, $change->value, $theirs);
        }

        if ($change instanceof RemoveBlock && $footprint->changedWithin($outline->keysWithin($change->key))) {
            return BlockClash::changed($change->key);
        }

        return null;
    }
}
