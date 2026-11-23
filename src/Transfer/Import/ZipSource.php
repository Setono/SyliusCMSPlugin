<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads an export from a zip holding the export's folder at its root. It reads files by their names and never extracts
 * the zip as a whole, and finds the entries that don't resolve inside the export's root, which refuse an import. It
 * unpacks no more than transfer.max_unpacked_size: what its directory says it unpacks to is checked before anything is
 * read from it, and what it reads and extracts is counted, since a zip's headers can lie, so it stops one byte past the
 * limit. See "Media files" in docs/spec/transfer.md
 */
final class ZipSource implements ExportSourceInterface
{
    /** The Unix file mode's type bits (S_IFMT) */
    private const FILE_TYPE = 0170000;

    /** The type of a symbolic link (S_IFLNK) */
    private const SYMBOLIC_LINK = 0120000;

    private readonly \ZipArchive $zip;

    /** Whether its directory was checked, which it is the first time it's to unpack anything */
    private bool $checked = false;

    /** How many bytes it has unpacked */
    private int $unpacked = 0;

    /**
     * @param int $maxUnpackedSize transfer.max_unpacked_size in bytes; no limit by default, for only seeing what it holds
     *
     * @throws InvalidExportException when the file isn't a zip that can be read
     */
    public function __construct(
        private readonly string $file,
        private readonly int $maxUnpackedSize = \PHP_INT_MAX,
    ) {
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

    /**
     * Reads no more of the file than its headers say it holds, which its directory's check bounds
     */
    public function read(string $path): string
    {
        $this->checkDirectory();
        $contents = $this->zip->getFromName($path);
        if (false === $contents) {
            throw new InvalidExportException(sprintf('The export has no readable file %s', $path));
        }

        $this->count(strlen($contents));

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

    /**
     * Extracts the file as far as it goes, whatever its headers say, but no further than one byte past what the zip may
     * still unpack
     */
    public function extract(string $path, $target, int $length): int
    {
        $this->checkDirectory();
        $left = $this->maxUnpackedSize - $this->unpacked;
        $stream = $this->zip->getStream($path);
        if (false === $stream) {
            throw new InvalidExportException(sprintf('The export has no readable file %s in %s', $path, $this->file));
        }

        try {
            // libzip fails a read that reaches the end of a file that doesn't match its checksum, with a warning
            $copied = @stream_copy_to_stream($stream, $target, $left < $length ? $left + 1 : $length);
        } finally {
            fclose($stream);
        }

        if (false === $copied) {
            throw new InvalidExportException(sprintf('The export\'s file %s can\'t be read from %s', $path, $this->file));
        }

        $this->count($copied);

        return $copied;
    }

    /**
     * Refuses the zip when its directory says it unpacks to more than it may, the first time it's to unpack anything
     *
     * @throws ExportTooBigException
     */
    private function checkDirectory(): void
    {
        if ($this->checked) {
            return;
        }

        $size = 0;
        for ($index = 0; $index < $this->zip->numFiles; ++$index) {
            $file = $this->zip->statIndex($index);
            $fileSize = false === $file ? 0 : $file['size'];
            // A size past PHP's integers reads as negative
            $size = $fileSize < 0 || $fileSize > \PHP_INT_MAX - $size ? \PHP_INT_MAX : $size + $fileSize;
        }

        if ($size > $this->maxUnpackedSize) {
            throw ExportTooBigException::declared($size, $this->maxUnpackedSize);
        }

        $this->checked = true;
    }

    /**
     * @throws ExportTooBigException once it has unpacked more than it may
     */
    private function count(int $bytes): void
    {
        $this->unpacked += $bytes;
        if ($this->unpacked > $this->maxUnpackedSize) {
            throw ExportTooBigException::unpacked($this->maxUnpackedSize);
        }
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
