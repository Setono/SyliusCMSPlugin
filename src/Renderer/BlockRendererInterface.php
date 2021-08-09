<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

interface BlockRendererInterface
{
    /**
     * @param string $block The code for the block
     */
    public function render(string $block): string;
}
