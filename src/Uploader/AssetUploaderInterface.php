<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface AssetUploaderInterface
{
    /**
     * Will take an Symfony\Component\HttpFoundation\File\UploadedFile and return the path where the asset was saved
     */
    public function uploadFile(UploadedFile $uploadedFile): string;
}
