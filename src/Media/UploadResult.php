<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * What became of an uploaded file: a new media item, or the existing media item whose current file has the same content,
 * which the editor is offered instead
 */
final readonly class UploadResult
{
    private function __construct(
        public MediaItemInterface $mediaItem,
        public bool $duplicate,
    ) {
    }

    public static function created(MediaItemInterface $mediaItem): self
    {
        return new self($mediaItem, false);
    }

    public static function duplicateOf(MediaItemInterface $mediaItem): self
    {
        return new self($mediaItem, true);
    }
}
