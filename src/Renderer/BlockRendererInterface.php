<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\BlockInterface;

interface BlockRendererInterface
{
    /**
     * @param BlockInterface|string $block Either the block object or the block code
     */
    public function render($block): string;
}
