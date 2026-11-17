<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * Builds a file's public URL from its storage path, without a call to the storage: under /media/cms, where the web
 * server serves the default storage's directory, %kernel.project_dir%/public/media/cms. A project whose storage is
 * elsewhere, such as on S3, aliases MediaUrlGeneratorInterface to its own service. See "Image processing" in
 * docs/spec/media.md
 */
final readonly class MediaUrlGenerator implements MediaUrlGeneratorInterface
{
    /** The URL the default storage's directory is served at */
    public const BASE_URL = '/media/cms';

    private string $baseUrl;

    public function __construct(string $baseUrl = self::BASE_URL)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function generate(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
