<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Opens an export folder or zip, as setono:cms:import and the Sylius fixture take either, and the zips uploaded on the
 * Transfer screen. A zip unpacks to no more than transfer.max_unpacked_size (ZipSource), while a folder, which isn't
 * unpacked, has no such limit. Either reads each JSON file no further than one byte past MAX_JSON_FILE_SIZE, which
 * refuses the import, as a JSON file is read whole into memory. See "Media files" in docs/spec/transfer.md
 */
final readonly class ExportSources
{
    /**
     * The cap on each JSON file an import reads, in bytes. It's fixed: the JSON file that grows with the size of the
     * site, the manifest, lists a picked record in 52 bytes, so 16 MB holds about 320,000 of them, far more than an
     * import holds in memory, which reads every entry it imports
     */
    public const MAX_JSON_FILE_SIZE = 16 * 1024 * 1024;

    /**
     * @param int $maxUnpackedSize transfer.max_unpacked_size in bytes
     * @param positive-int $maxJsonFileSize MAX_JSON_FILE_SIZE, which a test can lower
     */
    public function __construct(
        private int $maxUnpackedSize,
        private int $maxJsonFileSize = self::MAX_JSON_FILE_SIZE,
    ) {
    }

    /**
     * @throws InvalidExportException when the path is neither a folder nor a zip that can be read, or holds no manifest
     */
    public function open(string $path): ExportSourceInterface
    {
        if (is_dir($path)) {
            $source = new DirectorySource($path, $this->maxJsonFileSize);
        } elseif (is_file($path) && is_readable($path)) {
            $source = $this->zip($path);
        } else {
            throw new InvalidExportException(sprintf('%s is neither an export folder nor a zip', $path));
        }

        if (!$source->has(ExportFormat::MANIFEST)) {
            throw new InvalidExportException(sprintf('%s has no %s, so it isn\'t an export', $path, ExportFormat::MANIFEST));
        }

        return $source;
    }

    /**
     * @throws InvalidExportException when the file isn't a zip that can be read
     */
    public function zip(string $file): ZipSource
    {
        return new ZipSource($file, $this->maxUnpackedSize, $this->maxJsonFileSize);
    }
}
