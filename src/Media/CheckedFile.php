<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

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
