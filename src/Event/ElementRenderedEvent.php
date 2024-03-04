<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Setono\SyliusCMSPlugin\Stack\RenderedElement;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;

/**
 * This event is dispatched when an element is rendered
 */
final class ElementRenderedEvent
{
    public function __construct(
        /**
         * The rendered element
         */
        public readonly RenderedElement $element,
        /**
         * The template that was rendered and is represented by the rendered element
         */
        public readonly LogicalTemplateName $logicalTemplateName,
        /**
         * The twig context that was used when the element was rendered
         */
        public readonly array $context,
    ) {
    }
}
