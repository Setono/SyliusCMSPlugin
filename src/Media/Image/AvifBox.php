<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

/**
 * A box of an AVIF (an ISOBMFF file): its type, where its content starts, after its header, and where it ends
 *
 * @internal
 */
final readonly class AvifBox
{
    public function __construct(
        public string $type,
        public int $contentOffset,
        public int $end,
    ) {
    }
}
