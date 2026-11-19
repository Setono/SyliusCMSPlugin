<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Media\MediaFileCheckerInterface;
use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Checks a media item's file in an export, whether the media item is picked, a dependency or new in the target. An
 * export is untrusted input, so its filename must be a plain basename and the file must match its checksum, and then it
 * goes through the very checks an upload does (MediaFileCheckerInterface): its type against media.types, its size
 * against media.max_upload_size (UploadLimit::forImports(), which the limits of the PHP running the import don't lower),
 * its MIME type, size and dimensions read from the file, and an SVG sanitised. What the JSON says of the file is never
 * read. The file is copied out of the export into a temporary file for that, no further than that limit, so an export
 * can't fill the disk when one is configured. See "Media files" in docs/spec/transfer.md
 */
final readonly class ImportedFileChecker
{
    private UploadLimit $limit;

    public function __construct(
        private MediaFileCheckerInterface $checker,
        UploadLimit $uploadLimit,
    ) {
        $this->limit = $uploadLimit->forImports();
    }

    /**
     * @param bool $picked whether the export picked the media item, rather than it coming along as a dependency
     *
     * @throws RefusedFileException with the reason, which doesn't name the media item
     * @throws InvalidExportException when the export lacks the file
     */
    public function check(ExportSourceInterface $source, ExportedMediaItem $mediaItem, bool $picked): ReadMediaItem
    {
        $filename = $mediaItem->filename;
        if (!ExportFormat::isPlainFilename($filename)) {
            throw new RefusedFileException(new TranslatableMessage('setono_sylius_cms.import.media_file.filename', ['%filename%' => $filename]));
        }

        $copy = $this->copy($source, ExportFormat::mediaFilePath($mediaItem->uuid, $filename), $filename);

        try {
            if (!hash_equals($mediaItem->checksum, (string) hash_file('sha256', $copy))) {
                throw new RefusedFileException(new TranslatableMessage('setono_sylius_cms.import.media_file.checksum', ['%filename%' => $filename]));
            }

            return new ReadMediaItem($mediaItem, $this->checker->check(new \SplFileInfo($copy), $filename, $this->limit), $picked, $copy);
        } catch (\Throwable $e) {
            self::remove($copy);

            throw $e;
        }
    }

    /**
     * Copies the file out of the export into a temporary file, refusing it once it's over the upload limit
     *
     * @throws RefusedFileException
     * @throws InvalidExportException
     */
    private function copy(ExportSourceInterface $source, string $path, string $filename): string
    {
        $limit = $this->limit->bytes();
        $stream = $source->readStream($path);

        try {
            $copy = tempnam(sys_get_temp_dir(), 'setono_sylius_cms_import_');
            $target = false === $copy ? false : fopen($copy, 'wb');
            if (false === $copy || false === $target) {
                throw new \RuntimeException('A temporary file for an imported media file couldn\'t be written');
            }

            try {
                // One byte past the limit tells a file over it
                $copied = stream_copy_to_stream($stream, $target, $limit < \PHP_INT_MAX ? $limit + 1 : null);
            } finally {
                fclose($target);
            }
        } finally {
            fclose($stream);
        }

        if (false === $copied) {
            self::remove($copy);

            throw new InvalidExportException(sprintf('The export\'s file %s can\'t be read', $path));
        }

        if ($copied > $limit) {
            self::remove($copy);

            throw RefusedFileException::tooBig($filename, null, $limit);
        }

        return $copy;
    }

    private static function remove(string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
