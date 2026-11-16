<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The effective upload limit: media.max_upload_size, lowered to PHP's effective limit when it's higher, because PHP
 * refuses a bigger file before the plugin sees it. Without a configured limit, it's PHP's. The builder gets it with its
 * registry, and checks a file before uploading it. See "What the library holds" in docs/spec/media.md
 */
final readonly class UploadLimit
{
    /**
     * @param int|null $configured media.max_upload_size in bytes, or null for PHP's effective limit
     * @param int $php PHP's effective limit in bytes: the smaller of upload_max_filesize and post_max_size
     */
    public function __construct(
        private ?int $configured,
        private int $php,
    ) {
    }

    /**
     * Reads PHP's effective limit from the PHP that runs, which can differ from the one that built the container
     */
    public static function fromPhp(?int $configured): self
    {
        return new self($configured, (int) min(\PHP_INT_MAX, UploadedFile::getMaxFilesize()));
    }

    public function bytes(): int
    {
        return null === $this->configured ? $this->php : min($this->configured, $this->php);
    }

    public function allows(int $size): bool
    {
        return $size <= $this->bytes();
    }
}
