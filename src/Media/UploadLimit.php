<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The effective upload limit: media.max_upload_size, lowered to PHP's effective limit when it's higher, because PHP
 * refuses a bigger file before the plugin sees it. Without a configured limit, it's PHP's. The builder gets it with its
 * registry, and checks a file before uploading it. An import checks its files against forImports() instead, since it
 * doesn't upload them through PHP. See "What the library holds" in docs/spec/media.md
 */
final readonly class UploadLimit
{
    /**
     * @param int|null $configured media.max_upload_size in bytes, or null for no limit of its own
     * @param int $cap in bytes, what a higher configured limit is lowered to, and the limit without one: PHP's effective
     *                 limit for an upload, the smaller of upload_max_filesize and post_max_size, and
     *                 transfer.max_file_size for an import
     */
    public function __construct(
        private ?int $configured,
        private int $cap,
    ) {
    }

    /**
     * Reads PHP's effective limit from the PHP that runs, which can differ from the one that built the container
     */
    public static function fromPhp(?int $configured): self
    {
        return new self($configured, (int) min(\PHP_INT_MAX, UploadedFile::getMaxFilesize()));
    }

    /**
     * The limit an imported file is checked against: the smaller of the configured one and transfer.max_file_size. The
     * PHP that runs an import, such as the CLI's or the Messenger worker's, with its own upload_max_filesize, didn't
     * upload the file, so its limits don't lower it. See "Media files" in docs/spec/transfer.md
     *
     * @param int $maxFileSize transfer.max_file_size in bytes
     */
    public function forImports(int $maxFileSize): self
    {
        return new self($this->configured, $maxFileSize);
    }

    public function bytes(): int
    {
        return null === $this->configured ? $this->cap : min($this->configured, $this->cap);
    }

    public function allows(int $size): bool
    {
        return $size <= $this->bytes();
    }
}
