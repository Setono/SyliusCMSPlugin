<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Replaces a block's visibility conditions, in the stored condition format. See "Visibility conditions" in
 * docs/spec/content-model.md
 */
final readonly class SetConditions implements Change
{
    /**
     * @param string $key the block's key
     * @param list<array{kind: string, negate: bool, params: array<string, mixed>}> $conditions in order, combined with AND
     */
    public function __construct(
        public string $key,
        public array $conditions,
    ) {
    }
}
