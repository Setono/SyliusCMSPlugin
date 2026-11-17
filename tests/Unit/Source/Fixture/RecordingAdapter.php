<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;

/**
 * An adapter over a list that records whether it was counted and which slices were taken
 *
 * @template T
 * @implements AdapterInterface<T>
 */
final class RecordingAdapter implements AdapterInterface
{
    public bool $counted = false;

    /** @var list<array{int, int}> offset and length */
    public array $slices = [];

    /**
     * @param list<T> $items
     */
    public function __construct(private readonly array $items)
    {
    }

    public function getNbResults(): int
    {
        $this->counted = true;

        return count($this->items);
    }

    public function getSlice(int $offset, int $length): iterable
    {
        $this->slices[] = [$offset, $length];

        return array_slice($this->items, $offset, $length);
    }
}
