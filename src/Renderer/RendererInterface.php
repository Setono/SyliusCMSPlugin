<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @template T of ElementInterface
 */
interface RendererInterface
{
    /**
     * @param T|string $element Either the element object or the element code
     */
    public function render($element): Response;
}
