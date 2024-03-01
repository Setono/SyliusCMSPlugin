<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Uploader;

use Gaufrette\FilesystemInterface;
use Setono\SyliusCMSPlugin\Generator\PathGeneratorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AssetUploader implements AssetUploaderInterface
{
    public function __construct(private readonly FilesystemInterface $filesystem, private readonly PathGeneratorInterface $pathGenerator)
    {
    }

    public function uploadFile(UploadedFile $uploadedFile): string
    {
        do {
            $path = $this->pathGenerator->fromFile($uploadedFile);
        } while ($this->filesystem->has($path));

        $this->filesystem->write($path, file_get_contents($uploadedFile->getPathname()));

        return $path;
    }
}
