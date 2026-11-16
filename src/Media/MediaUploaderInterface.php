<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * Turns an uploaded file into a media item in a folder, or in Unsorted. See "Uploading" in docs/spec/media.md
 */
interface MediaUploaderInterface
{
    /**
     * Checks the file, and stores it as a new media item, unless its content matches an existing media item's current
     * file: then the answer is that media item, unless $copy says to upload a copy anyway
     *
     * @param string $originalFilename the name of the file that was uploaded, which names the media item
     *
     * @throws RefusedFileException
     */
    public function upload(\SplFileInfo $file, string $originalFilename, ?MediaFolderInterface $folder, bool $copy = false): UploadResult;
}
