<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

interface StringBasedTwigGeneratorInterface
{
    /**
     * Generates Twig based on the given input
     */
    public function generate(string $content): string;
}
