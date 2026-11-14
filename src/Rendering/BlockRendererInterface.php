<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * Renders one block. The default renderer renders the block type's template.
 * See "Block renderers" in docs/spec/extending.md
 */
interface BlockRendererInterface
{
    public function render(BlockView $view, RenderContext $context): string;
}
