<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads an export from a folder on disk. A path that would leave the folder, through "..", isn't one of its files
 */
final readonly class DirectorySource implements ExportSourceInterface
{
    public function __construct(private string $directory)
    {
    }

    public function has(string $path): bool
    {
        $file = $this->file($path);

        return null !== $file && is_file($file);
    }

    public function read(string $path): string
    {
        $file = $this->file($path);
        $contents = null === $file || !is_file($file) ? false : @file_get_contents($file);
        if (false === $contents) {
            throw new InvalidExportException(sprintf('The export has no readable file %s', $path));
        }

        return $contents;
    }

    public function files(string $directory): array
    {
        $path = $this->file($directory);
        if (null === $path || !is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (new \FilesystemIterator($path) as $file) {
            \assert($file instanceof \SplFileInfo);
            if ($file->isFile()) {
                $files[] = $file->getFilename();
            }
        }

        sort($files, \SORT_STRING);

        return $files;
    }

    public function readStream(string $path)
    {
        $file = $this->file($path);
        $stream = null === $file || !is_file($file) ? false : @fopen($file, 'rb');
        if (false === $stream) {
            throw new InvalidExportException(sprintf('The export has no readable file %s', $path));
        }

        return $stream;
    }

    private function file(string $path): ?string
    {
        if (in_array('..', explode('/', $path), true)) {
            return null;
        }

        return rtrim($this->directory, '/') . '/' . ltrim($path, '/');
    }
}
