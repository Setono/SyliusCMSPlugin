<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Asks the storage for the file's public URL, without a call to it: Flysystem builds it from its public_url option, as
 * the default storage sets it to /media/cms, or the adapter does, as the S3 adapter does
 */
final readonly class MediaUrlGenerator implements MediaUrlGeneratorInterface
{
    public function __construct(private FilesystemOperator $storage)
    {
    }

    public function generate(MediaItemInterface $mediaItem): string
    {
        if (!method_exists($this->storage, 'publicUrl')) {
            throw new \LogicException(sprintf('The media storage, a %s, has no public URLs. Give it a public_url option, or alias %s to a service of your own', get_debug_type($this->storage), MediaUrlGeneratorInterface::class));
        }

        $url = $this->storage->publicUrl($mediaItem->getPath());
        \assert(is_string($url));

        return $url;
    }
}
