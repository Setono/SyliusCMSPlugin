<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use ArrayIterator;

/**
 * @implements  \IteratorAggregate<array-key, ElementId>
 */
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

    public function getAssets(): array
    {
        return array_filter($this->elements, static fn (ElementId $element): bool => $element->isAsset());
    }

    public function getBlocks(): array
    {
        return array_filter($this->elements, static fn (ElementId $element): bool => $element->isBlock());
    }

    public function getCarousels(): array
    {
        return array_filter($this->elements, static fn (ElementId $element): bool => $element->isCarousel());
    }

    public function getPages(): array
    {
        return array_filter($this->elements, static fn (ElementId $element): bool => $element->isPage());
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
