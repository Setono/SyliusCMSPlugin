<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

/**
 * Where an item of an AVIF is stored, as its iloc box says: how its offsets are meant (0 into the file, 1 into the idat
 * box, 2 into another item), which file holds it (0 is this one), and its extents, each an offset and a length
 *
 * @internal
 */
final readonly class AvifItemLocation
{
    /**
     * @param list<array{int, int}> $extents
     */
    public function __construct(
        public int $constructionMethod,
        public int $dataReferenceIndex,
        public array $extents,
    ) {
    }
}
