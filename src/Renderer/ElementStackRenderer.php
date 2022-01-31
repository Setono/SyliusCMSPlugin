<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;

final class ElementStackRenderer implements RendererInterface
{
    private ElementStackInterface $elementStack;

    private RendererInterface $decoratedRenderer;

    public function __construct(ElementStackInterface $elementStack, RendererInterface $decoratedRenderer)
    {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->elementStack = $elementStack;
    }

    public function render($element): Response
    {
        $response = $this->decoratedRenderer->render($element);
        $this->elementStack->push($response->getElementIds());

        return $response;
    }
}
