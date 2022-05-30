<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use ArrayIterator;

final class ElementStack implements ElementStackInterface, \IteratorAggregate
{
    /** @var array<string, ElementId> */
    private array $elements = [];

    public function push($elements): void
    {
        if (!is_array($elements)) {
            $elements = [$elements];
        }

        foreach ($elements as $element) {
            if (isset($this->elements[$element->identifier])) {
                continue;
            }

            $this->elements[$element->identifier] = $element;
        }
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    public function hasElements(): bool
    {
        return !$this->isEmpty();
    }

    public function getBlocks(): array
    {
        return array_filter($this->elements, static function (ElementId $element): bool {
            return $element->isBlock();
        });
    }

    public function getCarousels(): array
    {
        return array_filter($this->elements, static function (ElementId $element): bool {
            return $element->isCarousel();
        });
    }

    public function getViews(): array
    {
        return array_filter($this->elements, static function (ElementId $element): bool {
            return $element->isView();
        });
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }

    public function count(): int
    {
        return count($this->elements);
    }
}
