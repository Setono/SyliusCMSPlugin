<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The cms_* Twig functions. See "Twig" in docs/spec/extending.md
 */
final class CmsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_field', [CmsRuntime::class, 'field'], ['is_safe' => ['html']]),
            new TwigFunction('cms_block', [CmsRuntime::class, 'block'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_blocks', [CmsRuntime::class, 'blocks'], ['is_safe' => ['html'], 'needs_context' => true]),
        ];
    }
}
