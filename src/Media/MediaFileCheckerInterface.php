<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * Checks a file the same way whether it's uploaded, replaced or imported: its size against the upload limit, its type
 * against media.types, its MIME type, dimensions and checksum read from the file itself, an SVG sanitised and an image's
 * EXIF orientation applied and its metadata stripped. Nothing is trusted from its name. See "Uploading" in
 * docs/spec/media.md
 */
interface MediaFileCheckerInterface
{
    /**
     * @param string $filename the file's original name, which only the messages use
     * @param UploadLimit|null $limit what the file's size is checked against: the upload limit, unless an import gives
     *                                its own (UploadLimit::forImports())
     *
     * @throws RefusedFileException
     */
    public function check(\SplFileInfo $file, string $filename, ?UploadLimit $limit = null): CheckedFile;
}
