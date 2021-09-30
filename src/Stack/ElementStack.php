<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use ArrayIterator;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;

final class ElementStack implements ElementStackInterface, \IteratorAggregate
{
    /** @var array<array-key, ElementInterface> */
    private array $elements = [];

    public function push(ElementInterface $element): void
    {
        $this->elements[] = $element;
    }

    public function hasElements(): bool
    {
        return count($this->elements) > 0;
    }

    public function getBlocks(): array
    {
        return array_filter($this->elements, static function (ElementInterface $element): bool {
            return $element instanceof BlockInterface;
        });
    }

    public function getViews(): array
    {
        return array_filter($this->elements, static function (ElementInterface $element): bool {
            return $element instanceof ViewInterface;
        });
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }
}
