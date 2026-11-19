<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;

/**
 * A media item of the export as the import read it: its JSON as it's written, and its file, checked as an upload is
 * and waiting in a temporary copy until the import stores it or is done. What the file is, its kind, MIME type, size,
 * dimensions and checksum, is what the check read from it, never what the JSON says
 */
final readonly class ReadMediaItem
{
    /**
     * @param bool $picked whether the export picked it, rather than it coming along as a dependency
     * @param string $copy the temporary copy of the file the export holds, which cleanUp() removes
     */
    public function __construct(
        public ExportedMediaItem $exported,
        public CheckedFile $file,
        public bool $picked,
        private string $copy,
    ) {
    }

    public function uuid(): string
    {
        return $this->exported->uuid;
    }

    /**
     * What names it: its name
     */
    public function label(): string
    {
        return $this->exported->name;
    }

    /**
     * Its alt text as the import writes it: an image's, by locale. Another kind has none, whatever the JSON says
     *
     * @return array<string, string>
     */
    public function altTexts(): array
    {
        if (MediaKind::Image !== $this->file->kind) {
            return [];
        }

        $altTexts = array_filter($this->exported->alt, static fn (string $altText): bool => '' !== trim($altText));
        ksort($altTexts);

        return $altTexts;
    }

    /**
     * Its focal point as the import writes it: an image's, the middle when the JSON has none. Another kind has none
     */
    public function focalPoint(): ?FocalPoint
    {
        if (MediaKind::Image !== $this->file->kind) {
            return null;
        }

        return $this->exported->focalPoint ?? new FocalPoint(0.5, 0.5);
    }

    /**
     * A video's poster's UUID. Another kind has none
     */
    public function poster(): ?string
    {
        return MediaKind::Video === $this->file->kind ? $this->exported->poster : null;
    }

    /**
     * Whether the target's media item holds the same file, of the same kind, and the metadata the import would write:
     * its name, folder, filename, credit, alt text, focal point and poster. Its MIME type, size and dimensions are the
     * file's, which the checksum covers
     */
    public function isIdenticalTo(MediaItemInterface $target): bool
    {
        $current = ExportedMediaItem::of($target);
        $focalPoint = $this->focalPoint();

        return $target->getKind() === $this->file->kind &&
            $target->getChecksum() === $this->file->checksum &&
            $current->name === $this->exported->name &&
            $current->folder === $this->exported->folder &&
            $current->filename === $this->exported->filename &&
            $current->credit === $this->exported->credit &&
            $current->alt === $this->altTexts() &&
            (null === $current->focalPoint || null === $focalPoint ? $current->focalPoint === $focalPoint : $current->focalPoint->equals($focalPoint)) &&
            $current->poster === $this->poster();
    }

    /**
     * Removes the temporary files of its file, once the import stored it or is done
     */
    public function cleanUp(): void
    {
        $this->file->cleanUp();
        if (is_file($this->copy)) {
            unlink($this->copy);
        }
    }
}
