<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transpiler;

interface BlockToTwigTranspilerInterface
{
    /**
     * Takes the block content and returns the transpiled Twig source
     */
    public function transpile(string $content): string;
}
