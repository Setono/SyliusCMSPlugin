<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

interface CarouselRendererInterface
{
    public function render($carousel): string;
}
