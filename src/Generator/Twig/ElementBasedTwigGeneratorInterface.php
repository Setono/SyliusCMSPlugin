<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @template T of ElementInterface
 */
interface ElementBasedTwigGeneratorInterface
{
    /**
     * Generates Twig based on the given element
     *
     * @param T $element
     */
    public function generate(ElementInterface $element): string;
}
