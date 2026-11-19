<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * A media item in an export, media/<uuid>.json, whose original file sits beside it at media/<uuid>/<filename>. See
 * "media/<uuid>.json" in docs/spec/transfer.md
 */
final readonly class ExportedMediaItem
{
    /**
     * @param string|null $folder the folder's UUID, or null for Unsorted
     * @param string $filename the original file's name, a plain basename
     * @param int|null $width in pixels, for images
     * @param int|null $height in pixels, for images
     * @param array<string, string> $alt the alt text by locale, for images
     * @param FocalPoint|null $focalPoint for images
     * @param string|null $poster a video's poster, an image media item's UUID
     * @param string $checksum the lowercase hex SHA-256 of the original file
     */
    public function __construct(
        public string $uuid,
        public ?string $folder,
        public MediaKind $kind,
        public string $name,
        public string $filename,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
        public ?string $credit,
        public array $alt,
        public ?FocalPoint $focalPoint,
        public ?string $poster,
        public string $checksum,
        public \DateTimeImmutable $changedAt,
    ) {
    }

    /**
     * The media item as an export holds it. Dimensions, alt text and the focal point are an image's only. Its file is
     * named by its original filename, or by the name it's stored under when the original isn't a plain basename an
     * import takes
     */
    public static function of(MediaItemInterface $mediaItem): self
    {
        $image = MediaKind::Image === $mediaItem->getKind();
        $filename = $mediaItem->getOriginalFilename();

        return new self(
            $mediaItem->getUuid(),
            $mediaItem->getFolder()?->getUuid(),
            $mediaItem->getKind(),
            $mediaItem->getName(),
            ExportFormat::isPlainFilename($filename) ? $filename : basename($mediaItem->getPath()),
            $mediaItem->getMimeType(),
            $mediaItem->getSize(),
            $image ? $mediaItem->getWidth() : null,
            $image ? $mediaItem->getHeight() : null,
            $mediaItem->getCredit(),
            $image ? $mediaItem->getAltTexts() : [],
            $image ? $mediaItem->getFocalPoint() : null,
            $mediaItem->getPoster()?->getUuid(),
            $mediaItem->getChecksum(),
            $mediaItem->getChangedAt(),
        );
    }
}
