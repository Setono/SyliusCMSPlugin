<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator;

use Setono\SyliusCMSPlugin\Model\Element;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Stack\RenderedElement;

final class ElementIdentifierGenerator implements ElementIdentifierGeneratorInterface
{
    public function generate(ElementInterface|RenderedElement $element): string
    {
        if ($element instanceof ElementInterface) {
            return sprintf('sscms-%s-%s', Element::getElementType($element, '-'), (string) $element->getCode());
        }

        return sprintf('sscms-%s-%s', $element->type, $element->code);
    }
}
