<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Uploader;

use Gaufrette\FilesystemInterface;
use Setono\SyliusCMSPlugin\Generator\PathGeneratorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AssetUploader implements AssetUploaderInterface
{
    private FilesystemInterface $filesystem;

    private PathGeneratorInterface $pathGenerator;

    public function __construct(FilesystemInterface $filesystem, PathGeneratorInterface $pathGenerator)
    {
        $this->filesystem = $filesystem;
        $this->pathGenerator = $pathGenerator;
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
