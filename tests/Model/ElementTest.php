<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Element;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\Page;

final class ElementTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideElements
     *
     * @param ElementInterface|class-string<ElementInterface> $element
     */
    public function it_returns_element_type(ElementInterface|string $element, string $expected): void
    {
        self::assertSame($expected, Element::getElementType($element));
        self::assertSame(str_replace('_', '-', $expected), Element::getElementType($element, '-'));
    }

    /**
     * @return \Generator<array-key, array{ElementInterface|class-string<ElementInterface>, string}>
     */
    public function provideElements(): \Generator
    {
        yield [new Page(), 'page'];
        yield [ConcreteElement::class, 'concrete_element'];
    }
}

final class ConcreteElement extends Element
{
}
