<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use League\Flysystem\FilesystemOperator;

/**
 * Deletes the file from the plugin's storage
 */
final readonly class MediaFileRemover implements MediaFileRemoverInterface
{
    public function __construct(private FilesystemOperator $storage)
    {
    }

    public function remove(string $path): void
    {
        $this->storage->delete($path);
    }
}
