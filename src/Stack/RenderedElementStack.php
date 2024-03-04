<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use ArrayIterator;

/**
 * @implements  \IteratorAggregate<array-key, RenderedElement>
 */
final class RenderedElementStack implements RenderedElementStackInterface, \IteratorAggregate
{
    /** @var array<string, RenderedElement> */
    private array $elements = [];

    public function push(array|RenderedElement $elements): void
    {
        if (!is_array($elements)) {
            $elements = [$elements];
        }

        foreach ($elements as $element) {
            $key = $element->type . $element->code;
            if (isset($this->elements[$key])) {
                continue;
            }

            $this->elements[$key] = $element;
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
        return array_filter($this->elements, static fn (RenderedElement $element): bool => $element->isAsset());
    }

    public function getBlocks(): array
    {
        return array_filter($this->elements, static fn (RenderedElement $element): bool => $element->isBlock());
    }

    public function getCarousels(): array
    {
        return array_filter($this->elements, static fn (RenderedElement $element): bool => $element->isCarousel());
    }

    public function getPages(): array
    {
        return array_filter($this->elements, static fn (RenderedElement $element): bool => $element->isPage());
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
