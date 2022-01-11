<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

interface ElementStackInterface extends \Traversable
{
    /**
     * Pushes an element onto the stack
     */
    public function push(Element $element): void;

    /**
     * Returns true if the stack has any views OR blocks
     */
    public function hasElements(): bool;

    /**
     * @return array<array-key, Element>
     */
    public function getBlocks(): array;

    /**
     * @return array<array-key, Element>
     */
    public function getCarousels(): array;

    /**
     * @return array<array-key, Element>
     */
    public function getViews(): array;
}
