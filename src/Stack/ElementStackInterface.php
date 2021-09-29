<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;

interface ElementStackInterface extends \Traversable
{
    /**
     * Pushes an element onto the stack
     */
    public function push(ElementInterface $element): void;

    /**
     * Returns true if the stack has any views OR blocks
     */
    public function hasElements(): bool;

    /**
     * @return array<array-key, BlockInterface>
     */
    public function getBlocks(): array;

    /**
     * @return array<array-key, ViewInterface>
     */
    public function getViews(): array;
}
