<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Twig\TokenParser\SectionTokenParser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class Extension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [
            new SectionTokenParser(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sscms_block', [Runtime::class, 'block'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_view', [Runtime::class, 'view'], ['is_safe' => ['html']]),
        ];
    }
}
