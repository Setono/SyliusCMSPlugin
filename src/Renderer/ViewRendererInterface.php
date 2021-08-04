<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

interface ViewRendererInterface
{
    /**
     * @param string $view The code for the view
     */
    public function render(string $view): string;
}
