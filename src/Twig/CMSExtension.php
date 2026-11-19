<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

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
            new TwigFunction('cms_value', [CMSRuntime::class, 'value'], ['needs_context' => true]),
            new TwigFunction('cms_block', [CMSRuntime::class, 'block'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_blocks', [CMSRuntime::class, 'blocks'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_card', [CardRuntime::class, 'card'], ['is_safe' => ['html'], 'needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('cms_slot', [SlotRuntime::class, 'slot'], ['is_safe' => ['html']]),
            new TwigFunction('cms_image', [ImageRuntime::class, 'image'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_video', [VideoRuntime::class, 'video'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_media_url', [MediaRuntime::class, 'url']),
            new TwigFunction('cms_link', [LinkRuntime::class, 'link']),
            new TwigFunction('cms_url', [LinkRuntime::class, 'url'], ['needs_context' => true]),
            new TwigFunction('cms_preview_mode', [CMSRuntime::class, 'previewMode']),
            new TwigFunction('cms_head_tags', [HeadTagsRuntime::class, 'headTags'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('cms_meta_title', [HeadTagsRuntime::class, 'metaTitle'], ['needs_context' => true]),
        ];
    }
}
