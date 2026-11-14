<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Json;

/**
 * A JSON object whose keys the spec lists, such as a block's "key", "type" and "version".
 * Canonical JSON writes its keys in the order given here instead of sorting them.
 */
final readonly class ListedObject
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(public array $properties)
    {
    }
}
