<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The MIME types each kind of media item allows, from media.types. A MIME type is in one kind at most.
 * See "What the library holds" in docs/spec/media.md
 */
final readonly class MediaTypes
{
    /** What editors call the shipped types. Others are called by their extension */
    private const NAMES = [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/gif' => 'GIF',
        'image/webp' => 'WebP',
        'image/avif' => 'AVIF',
        'image/svg+xml' => 'SVG',
        'video/mp4' => 'MP4',
        'video/webm' => 'WebM',
        'application/pdf' => 'PDF',
    ];

    /**
     * @param array<string, list<string>> $types MIME types by kind
     */
    public function __construct(private array $types)
    {
    }

    public function kindOf(string $mimeType): ?MediaKind
    {
        foreach ($this->types as $kind => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes, true)) {
                return MediaKind::from($kind);
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>> MIME types by kind, for every kind
     */
    public function all(): array
    {
        $all = [];
        foreach (MediaKind::cases() as $kind) {
            $all[$kind->value] = $this->types[$kind->value] ?? [];
        }

        return $all;
    }

    /**
     * What editors call a MIME type, such as JPEG for image/jpeg
     */
    public static function name(string $mimeType, string $extension): string
    {
        return self::NAMES[$mimeType] ?? strtoupper($extension);
    }
}
