<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The cms_* Twig functions. See "Twig" in docs/spec/extending.md
 */
final class CMSExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_field', [CMSRuntime::class, 'field'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_block', [CMSRuntime::class, 'block'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_blocks', [CMSRuntime::class, 'blocks'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_preview_mode', [PreviewContext::class, 'getMode']),
            new TwigFunction('cms_head_tags', [HeadTagsRuntime::class, 'headTags'], ['needs_context' => true]),
        ];
    }
}
