<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use ArrayIterator;

final class ElementStack implements ElementStackInterface, \IteratorAggregate
{
    /** @var array<string, Element> */
    private array $elements = [];

    public function push(Element $element): void
    {
        if (isset($this->elements[$element->getIdentifier()])) {
            return;
        }

        $this->elements[$element->getIdentifier()] = $element;
    }

    public function hasElements(): bool
    {
        return count($this->elements) > 0;
    }

    public function getBlocks(): array
    {
        return array_filter($this->elements, static function (Element $element): bool {
            return $element->isBlock();
        });
    }

    public function getCarousels(): array
    {
        return array_filter($this->elements, static function (Element $element): bool {
            return $element->isCarousel();
        });
    }

    public function getViews(): array
    {
        return array_filter($this->elements, static function (Element $element): bool {
            return $element->isView();
        });
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }
}
