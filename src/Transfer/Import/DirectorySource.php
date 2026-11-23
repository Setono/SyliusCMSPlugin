<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads an export from a folder on disk. A path that would leave the folder, through "..", isn't one of its files, and
 * a link in it refuses an import
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

    /**
     * Its links, files or folders, which it doesn't descend into. A path in a folder is never absolute and has no ".."
     * segment
     */
    public function pathsOutsideRoot(): array
    {
        $root = rtrim($this->directory, '/');
        if (!is_dir($root)) {
            return [];
        }

        $paths = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            if ($file->isLink()) {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($paths, \SORT_STRING);

        return $paths;
    }

    /**
     * Copies the file, as a folder isn't unpacked
     */
    public function extract(string $path, $target, int $length): int
    {
        $file = $this->file($path);

        return ExportFileCopy::copy(null === $file || !is_file($file) ? false : @fopen($file, 'rb'), $target, $length, $path);
    }

    private function file(string $path): ?string
    {
        if (!ExportPath::isInsideRoot($path)) {
            return null;
        }

        return rtrim($this->directory, '/') . '/' . ltrim($path, '/');
    }
}
