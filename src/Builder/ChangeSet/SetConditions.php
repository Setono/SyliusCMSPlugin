<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Replaces a block's visibility conditions, in the stored condition format. See "Visibility conditions" in
 * docs/spec/content-model.md
 */
final readonly class SetConditions implements Change
{
    public const OP = 'set_conditions';

    /**
     * @param string $key the block's key
     * @param list<array{kind: string, negate: bool, params: array<string, mixed>}> $conditions in order, combined with AND
     */
    public function __construct(
        public string $key,
        public array $conditions,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'key' => $this->key,
            'conditions' => array_map(static fn (array $condition): ListedObject => new ListedObject([
                'kind' => $condition['kind'],
                'negate' => $condition['negate'],
                'params' => (object) $condition['params'],
            ]), $this->conditions),
        ]);
    }
}
