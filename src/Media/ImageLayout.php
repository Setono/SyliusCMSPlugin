<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * How cms_image() sizes an image. Fixed renders at its width and offers 1x and 2x. Constrained renders up to its width
 * and shrinks with its container. Full width spans the viewport. See "The image helper" in docs/spec/media.md
 */
enum ImageLayout: string
{
    case Fixed = 'fixed';
    case Constrained = 'constrained';
    case FullWidth = 'full_width';

    /**
     * The widths the srcset offers the image at: of the ladder, those the layout needs, capped at the widest the source
     * gives, so it never upscales. A fixed image is offered at its width and twice it, while the source is that wide
     *
     * @param int<1, max> $width the rendered width
     * @param int<1, max> $maxWidth the widest the source gives at the aspect ratio
     * @param list<int<1, max>> $ladder media.widths, in ascending order
     *
     * @return non-empty-list<int<1, max>> in ascending order, each once
     */
    public function widths(int $width, int $maxWidth, array $ladder): array
    {
        if (self::Fixed === $this) {
            return 2 * $width <= $maxWidth ? [$width, 2 * $width] : [min($width, $maxWidth)];
        }

        $needed = $ladder;
        if (self::Constrained === $this) {
            // Up to the first width that covers the image at twice its width, for screens of twice the density
            $needed = [];
            foreach ($ladder as $ladderWidth) {
                $needed[] = $ladderWidth;
                if ($ladderWidth >= 2 * $width) {
                    break;
                }
            }
        }

        $widths = array_values(array_unique(array_map(static fn (int $ladderWidth): int => min($ladderWidth, $maxWidth), $needed)));
        sort($widths);

        return [] === $widths ? [min($width, $maxWidth)] : $widths;
    }

    /**
     * The sizes attribute: none for a fixed image, whose srcset gives densities
     */
    public function sizes(int $width): ?string
    {
        return match ($this) {
            self::Fixed => null,
            self::Constrained => sprintf('(min-width: %1$dpx) %1$dpx, 100vw', $width),
            self::FullWidth => '100vw',
        };
    }
}
