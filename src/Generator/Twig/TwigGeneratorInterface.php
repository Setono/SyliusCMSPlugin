<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @template T of ElementInterface
 */
interface TwigGeneratorInterface
{
    /**
     * Generates Twig based on the given element
     *
     * @param T $element
     * @param array<string, mixed> $context
     */
    public function generate(ElementInterface $element, array $context = []): string;

    /**
     * Returns true if the generator supports the given element
     *
     * @param T $element
     */
    public function supports(ElementInterface $element, array $context = []): bool;
}
