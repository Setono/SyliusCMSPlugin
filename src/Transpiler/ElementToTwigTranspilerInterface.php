<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transpiler;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @template T of ElementInterface
 */
interface ElementToTwigTranspilerInterface
{
    /**
     * Takes an element and transpiles that element into Twig
     *
     * @param T $element
     */
    public function transpile(ElementInterface $element): string;
}
