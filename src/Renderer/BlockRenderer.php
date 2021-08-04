<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

final class BlockRenderer implements BlockRendererInterface
{
    public function render(string $block): string
    {
        return '<h2>' . $block . '</h2>';
    }
}
