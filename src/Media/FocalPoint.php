<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The point of an image that must stay in view wherever it's cropped, as x and y from 0 to 1, from the top left.
 * See "Metadata" in docs/spec/media.md
 */
final readonly class FocalPoint
{
    /**
     * @throws \InvalidArgumentException when x or y is outside 0 to 1
     */
    public function __construct(
        public float $x = 0.5,
        public float $y = 0.5,
    ) {
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1 || is_nan($x) || is_nan($y)) {
            throw new \InvalidArgumentException(sprintf('A focal point\'s x and y must be from 0 to 1, not %s and %s', $x, $y));
        }
    }

    public static function center(): self
    {
        return new self();
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }
}
