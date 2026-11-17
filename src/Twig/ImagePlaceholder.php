<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

/**
 * The grey box cms_image() and cms_video() print for an empty image or video in the builder's preview, so it keeps its
 * place on the page. See "The image helper" in docs/spec/media.md
 *
 * @internal
 */
final class ImagePlaceholder
{
    /** The aspect ratio of a placeholder the template gives none for */
    public const ASPECT_RATIO = 16 / 9;

    private function __construct()
    {
    }

    /**
     * @param list<string|null> $classes the classes of what it stands in for, beside cms-image-placeholder
     * @param string $size more of its style, such as "; max-width: 400px"
     */
    public static function html(float $aspectRatio, array $classes = [], string $size = ''): string
    {
        return HtmlTag::open('span', [
            'class' => implode(' ', ['cms-image-placeholder', ...array_filter($classes, static fn (?string $class): bool => null !== $class && '' !== $class)]),
            'style' => 'aspect-ratio: ' . self::number($aspectRatio) . $size,
            'aria-hidden' => 'true',
        ]) . '</span>';
    }

    private static function number(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
