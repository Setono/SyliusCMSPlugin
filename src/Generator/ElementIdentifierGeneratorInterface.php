<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Stack\RenderedElement;

interface ElementIdentifierGeneratorInterface
{
    public function generate(ElementInterface|RenderedElement $element): string;
}
