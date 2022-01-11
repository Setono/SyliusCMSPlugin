<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Stack\Element;
use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;

final class ElementStackRenderer implements RendererInterface
{
    private ElementStackInterface $elementStack;

    private RendererInterface $decoratedRenderer;

    private string $elementType;

    public function __construct(
        ElementStackInterface $elementStack,
        RendererInterface $decoratedRenderer,
        string $elementType
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->elementStack = $elementStack;
        $this->elementType = $elementType;
    }

    public function render($element): Response
    {
        $response = $this->decoratedRenderer->render($element);

        if ($response instanceof SuccessfulResponse) {
            $this->elementStack->push(Element::fromSuccessfulResponse($response, $this->elementType));
        }

        return $response;
    }
}
