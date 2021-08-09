<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\ViewInterface;

interface ViewRendererInterface
{
    /**
     * @param ViewInterface|string $view Either the view object or the view code
     */
    public function render($view): string;
}
