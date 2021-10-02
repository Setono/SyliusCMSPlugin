<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use Setono\SyliusCMSPlugin\Filesystem\FilesystemInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;

final class ImagePreviewer implements PreviewerInterface
{
    private FilesystemInterface $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function preview(AssetInterface $asset): Preview
    {
        return new Preview(sprintf(
            '<img src="%s" alt="%s" style="width: 100%%">',
            $this->filesystem->resolveUrl((string) $asset->getPath()),
            (string) $asset->getName()
        ));
    }

    public function supports(AssetInterface $asset): bool
    {
        $mimeType = $asset->getMimeType();
        if (null === $mimeType) {
            return false;
        }

        return strpos($mimeType, 'image') !== false;
    }
}
