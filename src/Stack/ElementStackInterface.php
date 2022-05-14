<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

interface ElementStackInterface extends \Traversable, \Countable
{
    /**
     * Pushes an element onto the stack
     *
     * @param ElementId|array<array-key, ElementId> $elements
     */
    public function push($elements): void;

    public function isEmpty(): bool;

    /**
     * Returns true if the stack has any views OR blocks
     */
    public function hasElements(): bool;

    /**
     * @return array<array-key, ElementId>
     */
    public function getBlocks(): array;

    /**
     * @return array<array-key, ElementId>
     */
    public function getCarousels(): array;

    /**
     * @return array<array-key, ElementId>
     */
    public function getViews(): array;
}
