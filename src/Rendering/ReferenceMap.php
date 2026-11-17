<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * The records a block tree's references resolve to for the shopper, which ReferenceLoaderInterface loads before the
 * tree renders and field type handlers hydrate values from. A reference it doesn't hold is broken, or isn't visible to
 * the shopper, and rendering skips it. See "Rendering a block tree" in docs/spec/rendering.md
 */
final readonly class ReferenceMap
{
    /**
     * @param array<string, array<string, object>> $records by reference type, such as media_item, then by id: a code or a UUID
     */
    public function __construct(private array $records = [])
    {
    }

    /**
     * The record the reference resolves to, or null when it's broken or the shopper may not see it
     */
    public function get(string $type, string $id): ?object
    {
        return $this->records[$type][$id] ?? null;
    }

    public function has(string $type, string $id): bool
    {
        return isset($this->records[$type][$id]);
    }

    /**
     * Every record of the type it holds, by id
     *
     * @return array<string, object>
     */
    public function all(string $type): array
    {
        return $this->records[$type] ?? [];
    }

    /**
     * The map with the records of the type added, by id, such as those loaded in a pass of their own
     *
     * @param array<string, object> $records
     */
    public function with(string $type, array $records): self
    {
        if ([] === $records) {
            return $this;
        }

        $all = $this->records;
        $all[$type] = $records + ($all[$type] ?? []);

        return new self($all);
    }
}
