<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * The public URL of a media item's current file, in the plugin's storage. The permanent URL of documents and videos
 * redirects to it, and the library shows it. Templates render images through the image provider instead. A project
 * whose storage can't give public URLs aliases this interface to its own service
 */
interface MediaUrlGeneratorInterface
{
    public function generate(MediaItemInterface $mediaItem): string;
}
