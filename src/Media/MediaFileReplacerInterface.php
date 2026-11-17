<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Replaces a media item's file, which takes effect at once wherever it's used (ADR 0013). See "Replacing a file" in
 * docs/spec/media.md
 */
interface MediaFileReplacerInterface
{
    /**
     * The new file is checked like an upload and must be of the media item's kind. The media item keeps its UUID, name,
     * alt text and focal point, and its file gets a new storage path. After the transaction commits, the old file and
     * its variants are deleted by a delayed message once media.grace_period is over, and MediaItemReplaced is dispatched
     *
     * @param string $originalFilename the name the file was uploaded with
     *
     * @throws RefusedFileException when the file is refused, with the message editors see
     */
    public function replace(MediaItemInterface $mediaItem, \SplFileInfo $file, string $originalFilename): void;
}
