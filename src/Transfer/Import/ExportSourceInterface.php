<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Where an import reads an export's files from: a folder, or a zip holding the same folder at its root. Paths are
 * relative to the export's root, such as entries/<uuid>.json, as Format\ExportFormat names them. The mirror of
 * Transfer\ExportTargetInterface
 */
interface ExportSourceInterface
{
    public function has(string $path): bool;

    /**
     * Reads a JSON file of the export whole, no further than one byte past the cap on each JSON file an import reads,
     * ExportSources::MAX_JSON_FILE_SIZE
     *
     * @throws InvalidExportException when there's no such file, or it can't be read
     * @throws ExportTooBigException when the file is over the cap, or a zip unpacks to more than
     *                               transfer.max_unpacked_size
     */
    public function read(string $path): string;

    /**
     * The names of the files right in the directory, such as the entries' files in "entries", sorted
     *
     * @return list<string>
     */
    public function files(string $directory): array;

    /**
     * The paths of what it holds that doesn't resolve inside the export's root: an absolute path, a path with a ".."
     * segment, and a link, which an import refuses. See "Media files" in docs/spec/transfer.md
     *
     * @return list<string>
     */
    public function pathsOutsideRoot(): array;

    /**
     * Extracts a media item's original file into the stream, no more than $length bytes of it, so a file bigger than an
     * import takes can't fill the disk
     *
     * @param resource $target the caller opens and closes it
     *
     * @return int how many bytes it extracted
     *
     * @throws InvalidExportException when there's no such file, or it can't be read
     * @throws ExportTooBigException when a zip unpacks to more than transfer.max_unpacked_size
     */
    public function extract(string $path, $target, int $length): int;
}
