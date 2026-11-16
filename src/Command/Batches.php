<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

/**
 * Goes through every entry a batch at a time, for a command that drops a batch from the entity manager once it's done
 * with it, so the command's memory stays the same however many entries the store has
 *
 * @internal
 */
final class Batches
{
    /** How many entries a command goes through before it's done with a batch */
    public const SIZE = 100;

    private function __construct()
    {
    }

    /**
     * Yields each item, and calls $afterBatch once the caller is done with every SIZE-th one
     *
     * @template T
     *
     * @param iterable<T> $items
     * @param callable(): void $afterBatch
     *
     * @return \Generator<T>
     */
    public static function of(iterable $items, callable $afterBatch): \Generator
    {
        $batched = 0;
        foreach ($items as $item) {
            yield $item;

            if (self::SIZE === ++$batched) {
                $afterBatch();
                $batched = 0;
            }
        }
    }
}
