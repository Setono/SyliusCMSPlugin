<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Renderer\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Renderer\ViewRendererInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    private BlockRendererInterface $blockRenderer;

    private ViewRendererInterface $viewRenderer;

    public function __construct(BlockRendererInterface $blockRenderer, ViewRendererInterface $viewRenderer)
    {
        $this->blockRenderer = $blockRenderer;
        $this->viewRenderer = $viewRenderer;
    }

    public function block(string $block): string
    {
        return $this->blockRenderer->render($block);
    }

    public function view(string $view): string
    {
        return $this->viewRenderer->render($view);
    }
}
