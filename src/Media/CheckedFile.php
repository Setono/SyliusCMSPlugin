<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * A file that passed the checks, ready to store: what was read from it, and where its content is. An image's content is
 * the sanitised or stripped copy, in a temporary file that cleanUp() removes. A video's or a document's is the file as
 * it came
 */
final class CheckedFile
{
    public function __construct(
        public readonly MediaKind $kind,
        public readonly string $mimeType,
        /** The extension the stored file gets, from its MIME type */
        public readonly string $extension,
        /** Where the content to store is */
        public readonly string $path,
        public readonly int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        /** The SHA-256 of the content to store */
        public readonly string $checksum,
        private bool $temporary = false,
    ) {
    }

    /**
     * Points the media item at this file, stored at the path, as an upload, a replace and an import do: its original
     * filename, and its MIME type, size, dimensions and checksum as read from the file. Its metadata stays
     */
    public function applyTo(MediaItemInterface $mediaItem, string $originalFilename, string $path): void
    {
        $mediaItem->setOriginalFilename($originalFilename);
        $mediaItem->setPath($path);
        $mediaItem->setMimeType($this->mimeType);
        $mediaItem->setSize($this->size);
        $mediaItem->setDimensions($this->width, $this->height);
        $mediaItem->setChecksum($this->checksum);
    }

    /**
     * Writes the content to store at the path in the storage
     *
     * @throws \League\Flysystem\FilesystemException
     */
    public function store(FilesystemOperator $storage, string $path): void
    {
        $stream = fopen($this->path, 'rb');
        if (false === $stream) {
            throw new \RuntimeException(sprintf('The checked file %s couldn\'t be read', $this->path));
        }

        try {
            $storage->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Removes the temporary copy of an image, once it's stored or refused
     */
    public function cleanUp(): void
    {
        if ($this->temporary && is_file($this->path)) {
            unlink($this->path);
        }

        $this->temporary = false;
    }
}
