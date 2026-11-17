<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusCMSPlugin\Media\MediaFileRemoverInterface;

/**
 * Decorates the media file remover: after the file, it removes every image variant made from it, in each format,
 * through Liip's cache manager and the configured resolver. The file goes first, so no variant is made from it again in
 * between. A replaced file's old variants go once the grace period is over, and a deleted media item's at once.
 * See "Replacing a file" and "Deleting" in docs/spec/media.md
 *
 * @internal
 */
final readonly class VariantRemovingFileRemover implements MediaFileRemoverInterface
{
    public function __construct(
        private MediaFileRemoverInterface $decorated,
        private CacheManager $cacheManager,
    ) {
    }

    public function remove(string $path): void
    {
        $this->decorated->remove($path);

        $this->cacheManager->remove($path, FilterSets::all());
    }
}
