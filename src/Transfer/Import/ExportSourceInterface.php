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
     * @throws InvalidExportException when there's no such file, or it can't be read
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
     * A media item's original file
     *
     * @return resource read it to its end; the caller closes it
     *
     * @throws InvalidExportException when there's no such file, or it can't be read
     */
    public function readStream(string $path);
}
