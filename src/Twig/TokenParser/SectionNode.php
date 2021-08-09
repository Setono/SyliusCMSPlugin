<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\TokenParser;

use Twig\Compiler;
use Twig\Node\Node;

final class SectionNode extends Node
{
    public function __construct(string $name, int $line, string $tag = null)
    {
        parent::__construct([], ['name' => $name], $line, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write(sprintf('echo $context[\'%s\'] ?? \'\';', 'sscms_' . (string) $this->getAttribute('name')))
        ;
    }
}
