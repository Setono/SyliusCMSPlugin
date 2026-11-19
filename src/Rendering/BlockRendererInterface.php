<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * Renders one block through its block type's template. It's internal, not an extension point: a block type has no
 * renderer of its own, though one may come with a future sandboxed code block. See "Block templates" in
 * docs/spec/extending.md
 */
interface BlockRendererInterface
{
    public function render(BlockView $view, RenderContext $context): string;
}
