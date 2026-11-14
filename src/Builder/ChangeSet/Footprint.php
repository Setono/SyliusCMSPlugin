<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;

/**
 * What colleagues' change sets touched: the values they set, the blocks they set values on or added, and the blocks
 * they moved
 *
 * @internal
 */
final class Footprint
{
    /** @var array<string, true> */
    private array $values = [];

    /** @var array<string, true> */
    private array $changed = [];

    /** @var array<string, true> */
    private array $moved = [];

    public function __construct(ChangeSet $changeSet)
    {
        foreach ($changeSet->changes as $change) {
            if ($change instanceof SetValue) {
                $this->values[self::valueId($change)] = true;
                $this->changed[$change->key] = true;
            } elseif ($change instanceof AddBlock) {
                $this->changed += array_fill_keys($change->block->keys(), true);
            } elseif ($change instanceof MoveBlock) {
                $this->moved[$change->key] = true;
            }
        }
    }

    /**
     * Whether they set the same field in the same locale
     */
    public function hasValue(SetValue $change): bool
    {
        return isset($this->values[self::valueId($change)]);
    }

    /**
     * Whether deleting a block, with these keys of it and the blocks inside it, deletes something they changed:
     * a value they set, a block they added, or a block they moved into it. Moving the block itself changes nothing in it
     *
     * @param list<string> $keys the block's key first
     */
    public function changedWithin(array $keys): bool
    {
        foreach ($keys as $i => $key) {
            if (isset($this->changed[$key]) || ($i > 0 && isset($this->moved[$key]))) {
                return true;
            }
        }

        return false;
    }

    private static function valueId(SetValue $change): string
    {
        return CanonicalJson::encode([$change->key, $change->field, $change->locale]);
    }
}
