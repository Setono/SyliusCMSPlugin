<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads an export from a zip holding the export's folder at its root. It reads files by their names and never extracts
 * the zip, and finds the entries that don't resolve inside the export's root, which refuse an import
 */
final readonly class ZipSource implements ExportSourceInterface
{
    /** The Unix file mode's type bits (S_IFMT) */
    private const FILE_TYPE = 0170000;

    /** The type of a symbolic link (S_IFLNK) */
    private const SYMBOLIC_LINK = 0120000;

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

    /**
     * Its entries whose names are absolute or have a ".." segment, and its symbolic links, which a Unix zip stores as
     * an entry holding the link's target, marked by its external attributes. Reading by name never follows either, but
     * an export that holds one isn't one an import takes
     */
    public function pathsOutsideRoot(): array
    {
        $paths = [];
        for ($index = 0; $index < $this->zip->numFiles; ++$index) {
            $name = $this->zip->getNameIndex($index);
            if (false === $name) {
                continue;
            }

            if (!ExportPath::isInsideRoot($name) || $this->isLink($index)) {
                $paths[] = $name;
            }
        }

        return $paths;
    }

    public function readStream(string $path)
    {
        $stream = $this->zip->getStream($path);
        if (false === $stream) {
            throw new InvalidExportException(sprintf('The export has no readable file %s in %s', $path, $this->file));
        }

        return $stream;
    }

    private function isLink(int $index): bool
    {
        $system = $attributes = 0;
        if (!$this->zip->getExternalAttributesIndex($index, $system, $attributes) || \ZipArchive::OPSYS_UNIX !== $system || !is_int($attributes)) {
            return false;
        }

        // The upper 16 bits hold the Unix file mode, whose type bits say a symbolic link
        return self::SYMBOLIC_LINK === (($attributes >> 16) & self::FILE_TYPE);
    }
}
