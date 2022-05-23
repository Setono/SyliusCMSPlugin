<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transpiler;

final class BlockToTwigTranspiler implements BlockToTwigTranspilerInterface
{
    public function transpile(string $content): string
    {
        return sprintf(
            '{%% extends "@SetonoSyliusCMSPlugin/block.html.twig" %%}{%% block content %%}%s{%% endblock %%}',
            $content
        );
    }
}
