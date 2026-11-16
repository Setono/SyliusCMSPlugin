<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;

/**
 * What colleagues' change sets touched: the values they set, in blocks and in the entry, the blocks they set values or
 * styles on or added, and the blocks they moved. A block they removed is gone, so a change to it shows up as a missing
 * block instead
 *
 * @internal
 */
final class Footprint
{
    /** @var array<string, mixed> field and locale of a block => the last value they set there */
    private array $values = [];

    /** @var array<string, mixed> field and locale of the entry => the last value they set there */
    private array $entryValues = [];

    /** @var list<string> */
    private array $changed = [];

    /** @var list<string> */
    private array $moved = [];

    public function __construct(ChangeSet $changeSet)
    {
        foreach ($changeSet->changes as $change) {
            if ($change instanceof SetValue) {
                $this->values[self::valueId($change)] = $change->value;
                $this->changed[] = $change->key;
            }

            if ($change instanceof SetStyle) {
                $this->changed[] = $change->key;
            }

            if ($change instanceof SetEntryValue) {
                $this->entryValues[self::entryValueId($change)] = $change->value;
            }

            // The blocks inside an added block stay inside it, unless they're moved
            if ($change instanceof AddBlock) {
                $this->changed[] = $change->block->key;
            }

            if ($change instanceof MoveBlock) {
                $this->moved[] = $change->key;
            }
        }
    }

    /**
     * Whether they set the same field in the same locale
     */
    public function hasValue(SetValue $change): bool
    {
        return array_key_exists(self::valueId($change), $this->values);
    }

    /**
     * The last value they set in the same field and locale. Ask hasValue() first
     */
    public function valueOf(SetValue $change): mixed
    {
        return $this->values[self::valueId($change)];
    }

    /**
     * Whether they set the same entry field in the same locale
     */
    public function hasEntryValue(SetEntryValue $change): bool
    {
        return array_key_exists(self::entryValueId($change), $this->entryValues);
    }

    /**
     * The last value they set in the same entry field and locale. Ask hasEntryValue() first
     */
    public function entryValueOf(SetEntryValue $change): mixed
    {
        return $this->entryValues[self::entryValueId($change)];
    }

    /**
     * Whether deleting a block, with these keys of it and the blocks inside it, deletes something they changed:
     * a value they set, a block they added, or a block they moved into it. Moving the block itself changes nothing in it
     *
     * @param list<string> $keys the block's key first
     */
    public function changedWithin(array $keys): bool
    {
        return [] !== array_intersect($keys, $this->changed) || [] !== array_intersect(array_slice($keys, 1), $this->moved);
    }

    private static function valueId(SetValue $change): string
    {
        return CanonicalJson::encode([$change->key, $change->field, $change->locale]);
    }

    private static function entryValueId(SetEntryValue $change): string
    {
        return CanonicalJson::encode([$change->field, $change->locale]);
    }
}
