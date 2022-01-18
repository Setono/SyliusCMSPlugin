<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Renderer\ElementId;

interface ElementStackInterface extends \Traversable
{
    /**
     * Pushes an element onto the stack
     *
     * @param ElementId|array<array-key, ElementId> $elements
     */
    public function push($elements): void;

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
