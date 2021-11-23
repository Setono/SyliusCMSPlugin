<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\CarouselInterface;

interface CarouselRendererInterface
{
    /**
     * @param CarouselInterface|string $carousel Either the carousel object or the carousel code
     */
    public function render($carousel): string;
}
