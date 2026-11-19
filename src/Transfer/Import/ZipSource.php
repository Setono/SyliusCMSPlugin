<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads an export from a zip holding the export's folder at its root. It reads files by their names and never extracts
 * the zip. Checking that every entry of the zip resolves inside the export's root is #280's, with the media files
 */
final readonly class ZipSource implements ExportSourceInterface
{
    private \ZipArchive $zip;

    /**
     * @throws InvalidExportException when the file isn't a zip that can be read
     */
    public function __construct(private string $file)
    {
        $this->zip = new \ZipArchive();
        $opened = $this->zip->open($file, \ZipArchive::RDONLY);
        if (true !== $opened) {
            throw new InvalidExportException(sprintf('%s isn\'t a zip that can be read (error %d)', $file, $opened));
        }
    }

    public function has(string $path): bool
    {
        return false !== $this->zip->locateName($path);
    }

    public function read(string $path): string
    {
        $contents = $this->zip->getFromName($path);
        if (false === $contents) {
            throw new InvalidExportException(sprintf('The export has no readable file %s', $path));
        }

        return $contents;
    }

    public function files(string $directory): array
    {
        $prefix = rtrim($directory, '/') . '/';

        $files = [];
        for ($index = 0; $index < $this->zip->numFiles; ++$index) {
            $name = $this->zip->getNameIndex($index);
            if (false === $name || !str_starts_with($name, $prefix)) {
                continue;
            }

            $file = substr($name, strlen($prefix));
            if ('' !== $file && !str_contains($file, '/')) {
                $files[] = $file;
            }
        }

        sort($files, \SORT_STRING);

        return $files;
    }

    public function readStream(string $path)
    {
        $stream = $this->zip->getStream($path);
        if (false === $stream) {
            throw new InvalidExportException(sprintf('The export has no readable file %s in %s', $path, $this->file));
        }

        return $stream;
    }
}
