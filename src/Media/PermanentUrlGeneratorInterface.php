<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * The permanent URL of a document or a video, /media/{uuid}/{filename}, which redirects to its current file, so links
 * outside the shop, such as in a newsletter or on a printed QR code, survive a replace. Images have none, because
 * templates always render their current path. See "Permanent URLs" in docs/spec/media.md
 */
interface PermanentUrlGeneratorInterface
{
    /**
     * The absolute permanent URL, the same in every channel, with a filename made from the original filename. Null for
     * an image
     */
    public function generate(MediaItemInterface $mediaItem): ?string;
}
