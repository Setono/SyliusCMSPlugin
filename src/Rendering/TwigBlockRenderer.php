<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Twig\CmsRuntime;
use Twig\Environment;

/**
 * The default block renderer: renders the block type's template. See "Block templates" in docs/spec/extending.md
 */
final readonly class TwigBlockRenderer implements BlockRendererInterface
{
    public function __construct(
        private Environment $twig,
        private BlockTypeRegistryInterface $blockTypes,
    ) {
    }

    public function render(BlockView $view, RenderContext $context): string
    {
        return $this->twig->render($this->blockTypes->get($view->type)->template, [
            'block' => $view->block,
            'view' => $view,
            'children' => $view->children,
            'context' => $context->getHostPageContext(),
            CmsRuntime::RENDER_CONTEXT => $context,
        ]);
    }
}
